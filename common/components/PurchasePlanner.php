<?php

namespace common\components;

use common\models\IngredientStock;
use common\models\PurchasePlanningConfig;
use common\models\PurchasePresentation;
use common\models\PurchaseWeekLevel;
use yii\db\Query;

/**
 * Cálculo de la planeación de compras de un negocio para una semana.
 *
 * Lo usan la pantalla de planeación y la solicitud de compra a proveedores, para que ambas
 * muestren exactamente las mismas cantidades.
 *
 * Existencia, stock mínimo y máximo están en UNIDAD DE USO (portion_um): las entradas suman
 * cantidad × portions_per_unit y las salidas restan en unidad de uso.
 */
class PurchasePlanner
{
    /**
     * @param int $businessId
     * @param string $weekStart Lunes de la semana (Y-m-d).
     * @return array {
     *   rows: array[]            Un renglón por insumo con stock máximo (con o sin faltante).
     *   level: string, factor: float,
     *   totalStocks: int,        Insumos del negocio.
     *   sinStockIdeal: int       Insumos sin stock máximo (no se calculan).
     * }
     */
    public static function plan($businessId, $weekStart)
    {
        $businessId = (int)$businessId;
        $config = PurchasePlanningConfig::forBusiness($businessId);
        $level = PurchaseWeekLevel::levelFor($businessId, $weekStart);
        $factor = $config->getFactor($level);

        $stocks = IngredientStock::find()
            ->where(['business_id' => $businessId])
            ->orderBy('ingredient ASC')
            ->all();
        $stockIds = array_map(function ($s) {
            return (int)$s->id;
        }, $stocks);

        // Catálogo de compras (opcional) y último precio de compra de cada insumo.
        $catalog = PurchasePresentation::bestForIngredients($businessId, $stockIds);
        $lastPrices = self::lastPurchasePrices($stockIds);

        $rows = [];
        $sinStockIdeal = 0;
        foreach ($stocks as $stock) {
            $base = (float)$stock->max_stock;
            if ($base <= 0) {
                $sinStockIdeal++;
                continue;
            }
            $actual = (float)$stock->quantity;
            $ideal = $base * $factor;
            $faltante = max(0.0, $ideal - $actual);

            $row = [
                'id' => $stock->id,
                'nombre' => $stock->ingredient,
                'unidad' => $stock->portion_um ?: $stock->um,
                'actual' => $actual,
                'base' => $base,
                'ideal' => $ideal,
                'faltante' => $faltante,
                'compra' => null,
            ];
            if ($faltante > 0) {
                $row['compra'] = self::purchaseFor(
                    $stock,
                    $faltante,
                    $catalog[$stock->id] ?? null,
                    $lastPrices[$stock->id] ?? null
                );
            }
            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'level' => $level,
            'factor' => $factor,
            'totalStocks' => count($stocks),
            'sinStockIdeal' => $sinStockIdeal,
        ];
    }

    /**
     * Cuánto comprar de un insumo: convierte el faltante (en unidad de uso) a número de
     * presentaciones.
     *
     * Por defecto usa lo que el propio insumo ya define: su unidad de compra (um), la
     * equivalencia a unidades de uso (portions_per_unit) y su último precio de compra.
     * Si el negocio dio de alta una presentación en el catálogo de compras (opcional),
     * esa tiene prioridad.
     *
     * @param IngredientStock $stock
     * @param float $faltante En unidad de uso.
     * @param PurchasePresentation|null $presentation
     * @param float|null $lastPrice Último precio de compra por unidad de compra del insumo.
     * @return array|null null si no hay forma de convertir (sin catálogo ni equivalencia).
     */
    public static function purchaseFor(IngredientStock $stock, $faltante, $presentation, $lastPrice = null)
    {
        $ppu = (float)$stock->portions_per_unit;
        $lastPrice = ($lastPrice !== null && (float)$lastPrice > 0) ? (float)$lastPrice : null;
        // Precio de una unidad de uso, según el último precio de compra del insumo.
        $usePrice = ($lastPrice !== null && $ppu > 0) ? $lastPrice / $ppu : null;

        if ($presentation !== null) {
            $per = (float)$presentation->units_per_presentation;
            $n = PurchasePresentation::packagesFor($faltante, $per, (float)$presentation->min_order);
            $provider = $presentation->provider;

            if ($presentation->price !== null) {
                $cost = $n * (float)$presentation->price;
                $priceSource = 'catalogo';
            } elseif ($usePrice !== null) {
                $cost = $n * $per * $usePrice;
                $priceSource = 'insumo';
            } else {
                $cost = 0.0;
                $priceSource = null;
            }

            return [
                'origen' => 'catalogo',
                'presentacion_id' => $presentation->id,
                'nombre' => $presentation->name,
                'sku' => $presentation->sku,
                'proveedor_id' => $presentation->provider_id ? (int)$presentation->provider_id : null,
                'proveedor' => $provider ? ($provider->business_name ?: $provider->name) : null,
                'contenido' => $per,
                'cantidad' => $n,
                'precio_unitario' => $presentation->price !== null ? (float)$presentation->price : ($usePrice !== null ? $per * $usePrice : null),
                'costo' => $cost,
                'tiene_precio' => $priceSource !== null,
                'precio_origen' => $priceSource,
            ];
        }

        if ($ppu > 0 && $stock->um) {
            $n = PurchasePresentation::packagesFor($faltante, $ppu, 1);
            return [
                'origen' => 'insumo',
                'presentacion_id' => null,
                'nombre' => $stock->um,
                'sku' => null,
                'proveedor_id' => null,
                'proveedor' => null,
                'contenido' => $ppu,
                'cantidad' => $n,
                'precio_unitario' => $lastPrice,
                // El último precio ya es por unidad de compra: n × precio.
                'costo' => $lastPrice !== null ? $n * $lastPrice : 0.0,
                'tiene_precio' => $lastPrice !== null,
                'precio_origen' => $lastPrice !== null ? 'insumo' : null,
            ];
        }
        return null;
    }

    /**
     * Último precio de compra (por unidad de compra) de cada insumo, tomado del historial
     * stock_price con el mismo criterio que IngredientStock::getLastPrice() (fecha e id más recientes).
     * Una sola consulta para todos los insumos, no una por insumo.
     *
     * @param int[] $stockIds
     * @return array [stock_id => price]
     */
    public static function lastPurchasePrices(array $stockIds)
    {
        if (empty($stockIds)) {
            return [];
        }
        $rows = (new Query())
            ->select(['stock_id', 'price'])
            ->from('stock_price')
            ->where(['stock_id' => $stockIds])
            ->orderBy(['stock_id' => SORT_ASC, 'date' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $last = [];
        foreach ($rows as $r) {
            // Orden ascendente: la última fila de cada insumo es la más reciente.
            $last[(int)$r['stock_id']] = (float)$r['price'];
        }
        return $last;
    }

    /**
     * Minúsculas y sin acentos, para buscar sin que importen mayúsculas ni tildes ("cesar" encuentra "CÉSAR").
     */
    public static function fold($text)
    {
        return strtr(mb_strtolower(trim((string)$text), 'UTF-8'), [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    /**
     * Filtra renglones de la planeación por nombre de insumo. Cada palabra de la búsqueda debe
     * aparecer en el nombre (en cualquier orden), sin importar mayúsculas ni acentos.
     *
     * @param array $rows Renglones de {@see plan()}.
     * @param string $query
     * @return array
     */
    public static function filterByName(array $rows, $query)
    {
        $words = array_values(array_filter(preg_split('/\s+/u', self::fold($query)), 'strlen'));
        if (empty($words)) {
            return $rows;
        }
        return array_values(array_filter($rows, function ($row) use ($words) {
            $name = self::fold($row['nombre']);
            foreach ($words as $w) {
                if (strpos($name, $w) === false) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Último proveedor con el que se trabajó cada insumo, tomando lo más reciente entre:
     *  - las solicitudes de compra enviadas desde el sistema (purchase_request_item), y
     *  - las entradas de inventario (movement.type = input), donde el proveedor es texto libre: se
     *    liga al proveedor registrado por su nombre comercial o su nombre; si el nombre es ambiguo
     *    (lo comparten varios proveedores) se ignora en lugar de adivinar.
     *
     * @param int $businessId
     * @param int[] $ingredientIds
     * @return array [ingredient_id => ['provider_id' => int, 'source' => 'solicitud'|'compra', 'at' => int]]
     */
    public static function lastUsedProviders($businessId, array $ingredientIds)
    {
        $businessId = (int)$businessId;
        if (empty($ingredientIds)) {
            return [];
        }

        $fromRequests = [];
        $rows = (new Query())
            ->select(['i.ingredient_stock_id', 'i.provider_id', 'r.created_at'])
            ->from(['i' => 'purchase_request_item'])
            ->innerJoin(['r' => 'purchase_request'], 'r.id = i.request_id')
            ->where(['r.business_id' => $businessId, 'i.ingredient_stock_id' => $ingredientIds])
            ->andWhere(['not', ['i.provider_id' => null]])
            ->orderBy(['r.id' => SORT_ASC, 'i.id' => SORT_ASC])
            ->all();
        foreach ($rows as $r) {
            // Orden ascendente: la última fila de cada insumo es la más reciente.
            $fromRequests[(int)$r['ingredient_stock_id']] = [
                'provider_id' => (int)$r['provider_id'], 'source' => 'solicitud', 'at' => (int)$r['created_at'],
            ];
        }

        // Nombre de proveedor (texto libre de las entradas) -> id. Primero nombre comercial, luego nombre;
        // solo si identifica a un único proveedor.
        $byBusinessName = [];
        $byName = [];
        foreach ((new Query())->select(['id', 'name', 'business_name'])->from('provider')->where(['business_id' => $businessId])->all() as $p) {
            if (trim((string)$p['business_name']) !== '') {
                $byBusinessName[self::fold($p['business_name'])][] = (int)$p['id'];
            }
            if (trim((string)$p['name']) !== '') {
                $byName[self::fold($p['name'])][] = (int)$p['id'];
            }
        }
        $resolve = function ($label) use ($byBusinessName, $byName) {
            $key = self::fold($label);
            foreach ([$byBusinessName, $byName] as $map) {
                if (isset($map[$key]) && count(array_unique($map[$key])) === 1) {
                    return $map[$key][0];
                }
                if (isset($map[$key])) {
                    return null; // ambiguo
                }
            }
            return null;
        };

        $fromPurchases = [];
        $moves = (new Query())
            ->select(['ingredient_id', 'provider', 'created_at'])
            ->from('movement')
            ->where(['business_id' => $businessId, 'type' => 'input', 'ingredient_id' => $ingredientIds])
            ->andWhere(['not', ['provider' => null]])
            ->andWhere(['<>', 'provider', ''])
            ->orderBy(['id' => SORT_ASC])
            ->all();
        foreach ($moves as $m) {
            $pid = $resolve($m['provider']);
            if ($pid === null) {
                continue;
            }
            $fromPurchases[(int)$m['ingredient_id']] = [
                'provider_id' => $pid, 'source' => 'compra', 'at' => (int)strtotime((string)$m['created_at']),
            ];
        }

        $out = $fromRequests;
        foreach ($fromPurchases as $ingredientId => $purchase) {
            if (!isset($out[$ingredientId]) || $purchase['at'] > $out[$ingredientId]['at']) {
                $out[$ingredientId] = $purchase;
            }
        }
        return $out;
    }

    /**
     * Proveedores asignados a cada insumo (ingredient_provider), solo del negocio indicado.
     *
     * @param int $businessId
     * @param int[] $ingredientIds
     * @return array [ingredient_id => [provider_id => 'Nombre', ...]]
     */
    public static function providersByIngredient($businessId, array $ingredientIds)
    {
        if (empty($ingredientIds)) {
            return [];
        }
        $rows = (new Query())
            ->select(['ip.ingredient_id', 'p.id', 'p.business_name', 'p.name'])
            ->from(['ip' => 'ingredient_provider'])
            ->innerJoin(['p' => 'provider'], 'p.id = ip.provider_id')
            ->where(['p.business_id' => (int)$businessId, 'ip.ingredient_id' => $ingredientIds])
            ->orderBy(['ip.ingredient_id' => SORT_ASC, 'p.id' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['ingredient_id']][(int)$r['id']] = $r['business_name'] ?: $r['name'];
        }
        return $out;
    }
}
