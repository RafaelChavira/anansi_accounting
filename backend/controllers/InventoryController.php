<?php
namespace backend\controllers;

use Yii;
use common\models\Inventory;
use common\models\InventoryConsumptionCenter;
use common\models\ConsumptionCenter;
use backend\helpers\RedisKeys;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;

class InventoryController extends Controller 
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'delete-by-fecha' => ['POST'],
                ],
            ],
        ];
    }


    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Inventory::find(),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

public function actionCreate()
{
    $model = new Inventory();
    $businessData = \backend\helpers\RedisKeys::getValue(\backend\helpers\RedisKeys::BUSINESS_KEY);
    $businessId = $businessData['id'] ?? null;
    
    // Si es una petición GET (búsqueda o filtrado), solo mostrar la vista
    if (Yii::$app->request->isGet) {
        $fecha = Yii::$app->request->get('fecha');
        $inventarios = [];
        if ($fecha) {
            // Cargar datos existentes para edición
            $inventories = Inventory::find()->where(['fecha' => $fecha, 'business_id' => $businessId])->with('inventoryConsumptionCenters')->all();
            foreach ($inventories as $inv) {
                foreach ($inv->inventoryConsumptionCenters as $icc) {
                    $inventarios[$inv->ingredient_stock_id][$icc->consumption_center_id] = $icc->quantity;
                }
            }
            $model->fecha = $fecha;
        }
        return $this->render('create', [
            'model' => $model,
            'inventarios' => $inventarios,
            'fecha' => $fecha,
        ]);
    }
    
    // Si es POST, procesar el guardado del inventario
    if (Yii::$app->request->isPost) {
        $post = Yii::$app->request->post();
        $fecha = $post['Inventory']['fecha'] ?? null;
        $inventarios = $post['inventario'] ?? [];
        $errors = [];
        
        // Obtener todos los insumos del negocio
        $allStocks = \common\models\IngredientStock::find()->where(['business_id' => $businessId])->all();
        $allIds = array_map(function($stock) { return $stock->id; }, $allStocks);
        
        // Obtener centros de consumo del negocio
        $consumptionCenters = \common\models\ConsumptionCenter::find()->where(['business_id' => $businessId])->all();
        
        // Si es edición, eliminar registros existentes
        if ($fecha) {
            $existingInventories = Inventory::find()->where(['fecha' => $fecha, 'business_id' => $businessId])->all();
            foreach ($existingInventories as $inv) {
                InventoryConsumptionCenter::deleteAll(['inventory_id' => $inv->id]);
                $inv->delete();
            }
        }
        
        $dateEnd = date('Y-m-d H:i:s');
        foreach ($allIds as $insumoId) {
            $data = isset($inventarios[$insumoId]) ? $inventarios[$insumoId] : [];
            $inv = new Inventory();
            $inv->ingredient_stock_id = $insumoId;
            $inv->business_id = $businessId;
            $inv->fecha = $fecha;
            $inv->date_end = $dateEnd;
            
            if ($inv->save()) {
                foreach ($consumptionCenters as $center) {
                    $quantity = isset($data[$center->id]) && $data[$center->id] !== '' ? $data[$center->id] : 0;
                    $icc = new InventoryConsumptionCenter();
                    $icc->inventory_id = $inv->id;
                    $icc->consumption_center_id = $center->id;
                    $icc->quantity = $quantity;
                    if (!$icc->save()) {
                        $errors[$insumoId][] = 'Error saving for center ' . $center->name . ': ' . json_encode($icc->getErrors());
                    }
                }
            } else {
                $errors[$insumoId] = $inv->getErrors();
            }
        }
        
        if (empty($errors)) {
            Yii::$app->session->setFlash('success', 'Inventario guardado correctamente.');
            return $this->redirect(['index']);
        } else {
            Yii::$app->session->setFlash('error', 'Error al guardar el inventario.');
            // Puedes mostrar los errores específicos si lo necesitas
        }
    }
    
    return $this->render('create', [
        'model' => $model,
        'inventarios' => [],
    ]);
}

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }
        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionEdit($fecha)
    {
        $businessData = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        $businessId = $businessData['id'] ?? null;
        
        // Si es POST, procesar el guardado
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $inventarios = $post['inventario'] ?? [];
            $fechaInicial = $post['fecha_inicial'] ?? null;
            $fechaFinal = $post['fecha_final'] ?? null;
            $errors = [];
            
            // Validar que las fechas estén presentes
            if (!$fechaInicial || !$fechaFinal) {
                Yii::$app->session->setFlash('error', 'Las fechas inicial y final son obligatorias.');
                return $this->redirect(['edit', 'fecha' => $fecha]);
            }
            
            // Convertir fechas al formato correcto
            $fechaInicialFormatted = date('Y-m-d H:i:s', strtotime($fechaInicial));
            $fechaFinalFormatted = date('Y-m-d H:i:s', strtotime($fechaFinal));
            
            // Obtener centros de consumo del negocio
            $consumptionCenters = ConsumptionCenter::find()->where(['business_id' => $businessId])->all();
            
            // IMPORTANTE: Obtener TODOS los insumos del inventario original, no solo los que vienen en POST
            $existingInventories = Inventory::find()
                ->where(['fecha' => $fecha, 'business_id' => $businessId])
                ->with('inventoryConsumptionCenters')
                ->all();
            
            // Crear un array con todos los ingredient_stock_id del inventario original
            $allIngredientIds = [];
            foreach ($existingInventories as $inv) {
                $allIngredientIds[$inv->ingredient_stock_id] = $inv;
            }
            
            // Eliminar todos los registros existentes para esta fecha
            foreach ($existingInventories as $inv) {
                InventoryConsumptionCenter::deleteAll(['inventory_id' => $inv->id]);
                $inv->delete();
            }
            
            // Guardar TODOS los insumos (los que vienen en POST + los que no fueron modificados)
            foreach ($allIngredientIds as $ingredientId => $oldInventory) {
                $inv = new Inventory();
                $inv->ingredient_stock_id = $ingredientId;
                $inv->business_id = $businessId;
                $inv->fecha = $fechaInicialFormatted;
                $inv->date_end = $fechaFinalFormatted;
                
                if ($inv->save()) {
                    foreach ($consumptionCenters as $center) {
                        // Si el insumo viene en POST, usar esos valores
                        if (isset($inventarios[$ingredientId][$center->id])) {
                            $quantity = $inventarios[$ingredientId][$center->id] !== '' ? $inventarios[$ingredientId][$center->id] : 0;
                        } else {
                            // Si no viene en POST, mantener los valores originales
                            $quantity = 0;
                            foreach ($oldInventory->inventoryConsumptionCenters as $oldIcc) {
                                if ($oldIcc->consumption_center_id == $center->id) {
                                    $quantity = $oldIcc->quantity;
                                    break;
                                }
                            }
                        }
                        
                        $icc = new InventoryConsumptionCenter();
                        $icc->inventory_id = $inv->id;
                        $icc->consumption_center_id = $center->id;
                        $icc->quantity = $quantity;
                        if (!$icc->save()) {
                            $errors[$ingredientId][] = 'Error saving for center ' . $center->name;
                        }
                    }
                } else {
                    $errors[$ingredientId] = $inv->getErrors();
                }
            }
            
            if (empty($errors)) {
                Yii::$app->session->setFlash('success', 'Inventario actualizado correctamente.');
                return $this->redirect(['detalle', 'fecha' => $fechaInicialFormatted]);
            } else {
                Yii::$app->session->setFlash('error', 'Error al actualizar el inventario.');
            }
        }
        
        // Cargar datos para mostrar (similar a actionDetalle)
        $searchModel = new \common\models\InventorySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $fecha);
        
        // Desactivar la paginación para edición (mostrar todos los elementos)
        $dataProvider->pagination = false;
        
        // Obtener la fecha de finalización del primer registro
        $firstInventory = Inventory::find()->where(['fecha' => $fecha])->orderBy(['id' => SORT_ASC])->one();
        $dateEnd = $firstInventory ? $firstInventory->date_end : null;
        
        // Centros de consumo del negocio
        $consumptionCenters = ConsumptionCenter::find()->where(['business_id' => $businessId])->orderBy(['id' => SORT_ASC])->all();
        
        return $this->render('edit', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
            'fecha' => $fecha,
            'dateEnd' => $dateEnd,
            'consumptionCenters' => $consumptionCenters,
        ]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        return $this->redirect(['index']);
    }

    public function actionDeleteByFecha($fecha)
    {
        $businessData = \backend\helpers\RedisKeys::getValue(\backend\helpers\RedisKeys::BUSINESS_KEY);
        $businessId = $businessData['id'] ?? null;
        
        // Buscar todos los inventarios para esta fecha y negocio
        $inventories = Inventory::find()->where(['fecha' => $fecha, 'business_id' => $businessId])->all();
        
        if (empty($inventories)) {
            Yii::$app->session->setFlash('error', 'No se encontraron inventarios para la fecha especificada.');
            return $this->redirect(['index']);
        }
        
        $deletedCount = 0;
        
        // Eliminar cada inventario y sus registros relacionados
        foreach ($inventories as $inventory) {
            // Eliminar registros de InventoryConsumptionCenter
            InventoryConsumptionCenter::deleteAll(['inventory_id' => $inventory->id]);
            
            // Eliminar el inventario
            if ($inventory->delete()) {
                $deletedCount++;
            }
        }
        
        if ($deletedCount > 0) {
            $fechaFormateada = date('d/m/Y H:i', strtotime($fecha));
            Yii::$app->session->setFlash('success', "Inventario del {$fechaFormateada} eliminado correctamente. Se eliminaron {$deletedCount} registros.");
        } else {
            Yii::$app->session->setFlash('error', 'No se pudieron eliminar los registros de inventario.');
        }
        
        return $this->redirect(['index']);
    }

    protected function findModel($id)
    {
        if (($model = Inventory::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested Inventory does not exist.');
    }
    public function actionDetalle($fecha)
    {
        $searchModel = new \common\models\InventorySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $fecha);
        
        // Obtener el valor de la cookie si existe
        $savedPageSize = (int)Yii::$app->request->cookies->getValue('inventory_detalle_page_size', 20);
        
        // Personalizar elementos por página - solo si viene en la URL
        $perPage = Yii::$app->request->get('per-page');
        
        // Si perPage no viene en la URL o no es válido, usar el valor guardado en la cookie
        if (!$perPage || !in_array((int)$perPage, [20, 50, 100, 500])) {
            $perPage = in_array($savedPageSize, [20, 50, 100, 500]) ? $savedPageSize : 20;
        } else {
            // Solo guardar una nueva cookie si el valor es diferente al que ya tenemos
            if ((int)$perPage !== $savedPageSize) {
                $cookie = new \yii\web\Cookie([
                    'name' => 'inventory_detalle_page_size',
                    'value' => (int)$perPage,
                    'expire' => time() + 86400 * 30,
                ]);
                Yii::$app->response->cookies->add($cookie);
            }
        }
        
        // Usar perPage como la cantidad de elementos por página
        $pageSize = (int)$perPage;
        $dataProvider->pagination->pageSize = $pageSize;
        
        // Obtener la fecha de finalización del primer registro de inventario para esa fecha
        $firstInventory = \common\models\Inventory::find()->where(['fecha' => $fecha])->orderBy(['id' => SORT_ASC])->one();
        $dateEnd = $firstInventory ? $firstInventory->date_end : null;

        // Obtener business_id (misma fuente que en otros métodos)
        $businessData = \backend\helpers\RedisKeys::getValue(\backend\helpers\RedisKeys::BUSINESS_KEY);
        $businessId = $businessData['id'] ?? null;

        // Centros de consumo del negocio
        $consumptionCenters = ConsumptionCenter::find()->where(['business_id' => $businessId])->orderBy(['id' => SORT_ASC])->all();

        // Cargar modelos (con relaciones) para calcular totales dinámicos
        $query = $dataProvider->query->with(['inventoryConsumptionCenters', 'ingredientStock']);
        $allModels = $query->all();

        $totalInventario = 0;
        $totalDinero = 0;
        $totalesPorCentroCantidad = [];
        $totalesPorCentroCosto = [];

        // Preparar totales por áreas fijas (almacen, cocina, barra, servicio, otro)
        $areasFixed = ['almacen', 'cocina', 'barra', 'servicio', 'otro'];
        $cantidadesPorArea = array_fill_keys($areasFixed, 0);
        $totalesPorArea = array_fill_keys($areasFixed, 0);

        // Mapa de id => nombre para centros
        $centerNameMap = [];
        foreach ($consumptionCenters as $c) {
            $centerNameMap[$c->id] = mb_strtolower($c->name);
        }

        if (!empty($consumptionCenters)) {
            foreach ($consumptionCenters as $c) {
                $totalesPorCentroCantidad[$c->id] = 0;
                $totalesPorCentroCosto[$c->id] = 0;
            }

            foreach ($allModels as $model) {
                // calcular suma por modelo (todos los centros)
                $sumaModel = 0;
                // lastUnitPrice devuelve adjusted_price que ya ES el precio por porción
                $precio = ($model->ingredientStock && isset($model->ingredientStock->lastUnitPrice)) ? $model->ingredientStock->lastUnitPrice : 0;
                //var_dump('precio: ' . $precio .''. 'porciones: ' . $model->ingredientStock->portions_per_unit);
                foreach ($model->inventoryConsumptionCenters as $icc) {
                    $sumaModel += $icc->quantity;
                    if (isset($totalesPorCentroCantidad[$icc->consumption_center_id])) {
                        $totalesPorCentroCantidad[$icc->consumption_center_id] += $icc->quantity;
                        $totalesPorCentroCosto[$icc->consumption_center_id] += ($icc->quantity * $precio);
                    }
                    // mapear cantidad también a área fija según el nombre del centro
                    $centerName = $centerNameMap[$icc->consumption_center_id] ?? '';
                    $assigned = false;
                    if ($centerName !== '') {
                        if (mb_strpos($centerName, 'almac') !== false) { // almacén
                            $cantidadesPorArea['almacen'] += $icc->quantity;
                            $totalesPorArea['almacen'] += $icc->quantity * $precio;
                            $assigned = true;
                        } elseif (mb_strpos($centerName, 'cocina') !== false) {
                            $cantidadesPorArea['cocina'] += $icc->quantity;
                            $totalesPorArea['cocina'] += $icc->quantity * $precio;
                            $assigned = true;
                        } elseif (mb_strpos($centerName, 'barra') !== false) {
                            $cantidadesPorArea['barra'] += $icc->quantity;
                            $totalesPorArea['barra'] += $icc->quantity * $precio;
                            $assigned = true;
                        } elseif (mb_strpos($centerName, 'servi') !== false) {
                            $cantidadesPorArea['servicio'] += $icc->quantity;
                            $totalesPorArea['servicio'] += $icc->quantity * $precio;
                            $assigned = true;
                        }
                    }
                    if (!$assigned) {
                        $cantidadesPorArea['otro'] += $icc->quantity;
                        $totalesPorArea['otro'] += $icc->quantity * $precio;
                    }
                }
                $totalInventario += $sumaModel;
                $totalDinero += $sumaModel * $precio;
            }
        } else {
            // Fallback: calcular usando campos fijos antigos
            $areas = ['almacen', 'cocina', 'barra', 'servicio', 'otro'];
            $totalesPorArea = [];
            $cantidadesPorArea = [];
            foreach ($areas as $area) {
                $totalesPorArea[$area] = 0;
                $cantidadesPorArea[$area] = 0;
            }
            foreach ($allModels as $model) {
                $total = $model->inventario_almacen + $model->inventario_cocina + $model->inventario_barra + $model->inventario_servicio + $model->inventario_otro;
                $precio = ($model->ingredientStock && isset($model->ingredientStock->lastUnitPrice) && isset($model->ingredientStock->portions_per_unit)) ? $model->ingredientStock->lastUnitPrice / $model->ingredientStock->portions_per_unit : 0;
                $totalInventario += $total;
                $totalDinero += $total * $precio;
                foreach ($areas as $area) {
                    $cantidad = isset($model->{'inventario_' . $area}) ? $model->{'inventario_' . $area} : 0;
                    $cantidadesPorArea[$area] += $cantidad;
                    $totalesPorArea[$area] += $cantidad * $precio;
                }
            }
        }

        return $this->render('detalle', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
            'fecha' => $fecha,
            'dateEnd' => $dateEnd,
            'consumptionCenters' => $consumptionCenters,
            'totalesPorCentroCantidad' => $totalesPorCentroCantidad,
            'totalesPorCentroCosto' => $totalesPorCentroCosto,
            'totalesPorArea' => $totalesPorArea,
            'cantidadesPorArea' => $cantidadesPorArea,
            'totalInventario' => $totalInventario,
            'totalDinero' => $totalDinero,
        ]);
    }
    /**
     * Exporta una plantilla de inventario en Excel con todos los insumos y columnas de existencias.
     * La fecha actual se pone en la primera fila y se protege para que no se pueda modificar.
     */
public function actionExportPlantillaInventario()
{
    $businessData = \backend\helpers\RedisKeys::getValue(\backend\helpers\RedisKeys::BUSINESS_KEY);
    $businessId = $businessData['id'] ?? null;
    
    // Obtener centros de consumo del usuario
    $consumptionCenters = \common\models\ConsumptionCenter::find()
        ->where(['business_id' => $businessId])
        ->orderBy(['id' => SORT_ASC])
        ->all();
    
    $insumos = \common\models\IngredientStock::find()->where(['business_id' => $businessId])->all();

    // Usar la fecha enviada por el usuario o la del servidor como fallback
    $fechaActual = Yii::$app->request->get('fecha', date('Y-m-d H:i'));

    // Crear el documento
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Inventario');

    // ====== BLOQUEAR NUEVAS HOJAS ======
    // Eliminar hojas por defecto y mantener solo nuestra hoja
    $sheetCount = $spreadsheet->getSheetCount();
    for ($i = $sheetCount - 1; $i > 0; $i--) {
        $spreadsheet->removeSheetByIndex($i);
    }

    // Configurar protección del libro para evitar nuevas hojas
    //$spreadsheet->getSecurity()->setLockStructure(true);
    //$spreadsheet->getSecurity()->setLockWindows(true);
    // setRevisions() fue eliminado ya que no existe

    // ====== 2. FECHA ======
    $sheet->setCellValue('A2', 'Fecha de generación:');
    $sheet->setCellValue('B2', $fechaActual);
    $sheet->getStyle('A2:B2')->getFont()->setBold(true);
    //$sheet->getStyle('B2')->getProtection()->setLocked(true); // Solo la fecha protegida

    // ====== 2.1. ALERTA TEMPORAL ======
    $sheet->mergeCells('A3:H3');
    $sheet->setCellValue('A3', '⚠️ IMPORTANTE: Esta plantilla solo se puede importar HOY o MAÑANA. Después de ese tiempo será rechazada automáticamente.');
    $sheet->getStyle('A3')->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'D32F2F'], // Rojo
            'size' => 10
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFEBEE'] // Fondo rojo claro
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        ],
    ]);

    // ====== 3. ENCABEZADOS ======
    $headers = [
        'Insumo', 
        'Unidad', 
        'Categoría'
    ];
    
    // Agregar encabezados dinámicos para cada centro de consumo
    foreach ($consumptionCenters as $center) {
        $headers[] = 'Existencia ' . $center->name;
    }
    
    $headerRow = 5;

    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, $headerRow, $header);
        $col++;
    }

    // Estilo de encabezados
    $lastCol = chr(64 + count($headers)); // Convertir número de columna a letra (A, B, C, etc.)
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFFFFF']
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ]
    ]);

    // ====== 4. CONTENIDO ======
    $row = $headerRow + 1;
    foreach ($insumos as $insumo) {
        $sheet->setCellValue("A{$row}", $insumo->ingredient);
        $sheet->setCellValue("B{$row}", $insumo->portion_um);
        $sheet->setCellValue("C{$row}", $insumo->category ? $insumo->category->name : '-');

        // Columnas vacías para existencias de cada centro de consumo
        $col = 4; // Empezar desde la columna D
        foreach ($consumptionCenters as $center) {
            $sheet->setCellValueByColumnAndRow($col, $row, '');
            $col++;
        }
        $row++;
    }

    $lastRow = $row - 1;

    // Estilo para el contenido
    $sheet->getStyle("A" . ($headerRow + 1) . ":{$lastCol}{$lastRow}")->applyFromArray([
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'DDDDDD']
            ]
        ]
    ]);

    // ====== VALIDACIÓN DE DATOS PARA EXISTENCIAS ======
    for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
        $col = 4; // Empezar desde la columna D
        foreach ($consumptionCenters as $center) {
            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $r;
            $validation = $sheet->getCell($cellCoordinate)->getDataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
            $validation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHANOREQUAL);
            $validation->setFormula1(0);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(false);
            $validation->setShowErrorMessage(true);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $validation->setErrorTitle('Valor inválido');
            $validation->setError('Solo se permiten números positivos o cero en esta celda.');
            $col++;
        }
    }

    // ====== 5. AUTOAJUSTE DE COLUMNAS ======
    $colIndex = 1;
    foreach ($headers as $header) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        $colIndex++;
    }

    // ====== 6. CONGELAR FILAS DE ENCABEZADOS ======
    $sheet->freezePane("A6");

    // ====== 7. OCULTAR CUADRÍCULA ======
    $sheet->setShowGridlines(false);

    // ====== 8. PROTECCIÓN (solo fecha y alerta bloqueadas) ======
    //$protection = $sheet->getProtection();
    //$protection->setSheet(true);
    //$protection->setPassword('inventario');
    //$sheet->getStyle('A2:B2')->getProtection()->setLocked(true); // Fecha protegida
    //$sheet->getStyle('A3:H3')->getProtection()->setLocked(true); // Alerta protegida
    //$sheet->getStyle("A6:{$lastCol}{$lastRow}")->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);

    // ====== 9. CONFIGURACIÓN ADICIONAL PARA BLOQUEAR HOJAS ======
    // Configurar protección del libro con contraseña
    $spreadsheet->getSecurity()->setLockStructure(true);
    $spreadsheet->getSecurity()->setLockWindows(true);
    $spreadsheet->getSecurity()->setWorkbookPassword('inventario');

    // ====== 10. EXPORTAR ARCHIVO ======
    $filename = 'plantilla_inventario_' . $fechaActual . '.xlsx';
    \Yii::$app->response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    \Yii::$app->response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
    \Yii::$app->response->headers->set('Cache-Control', 'max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    ob_start();
    $writer->save('php://output');
    $content = ob_get_clean();
    \Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
    \Yii::$app->response->content = $content;
    return \Yii::$app->response;
}

 /**
     * Importa los datos de inventario desde la plantilla Excel exportada.
     */
    public function actionImportPlantillaInventario()
    {
        $file = UploadedFile::getInstanceByName('inventory-file');
        if (Yii::$app->request->isPost) {
            if ($file) {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->tempName);
                $sheet = $spreadsheet->getActiveSheet();

                // Leer fecha de la celda B2
                $fecha = $sheet->getCell('B2')->getValue();
                if (!$fecha) {
                    Yii::$app->session->setFlash('error', 'No se encontró la fecha en la plantilla.');
                    return $this->redirect(['index']);
                }

                // Validar que la plantilla no sea muy antigua (máximo 2 días)
                // Usar la fecha del usuario (fecha_importacion) para la comparación, no la del servidor
                $fechaUsuario = Yii::$app->request->post('fecha_importacion', date('Y-m-d H:i:s'));
                $fechaPlantilla = new \DateTime($fecha);
                $fechaActualUsuario = new \DateTime($fechaUsuario);
                $diferenciaDias = $fechaActualUsuario->diff($fechaPlantilla)->days;
                
                /*if ($diferenciaDias > 1) {
                    $fechaFormateada = $fechaPlantilla->format('d/m/Y H:i');
                    Yii::$app->session->setFlash('error', "Esta plantilla es muy antigua. Fue descargada el {$fechaFormateada}. Solo se pueden importar plantillas descargadas hoy o ayer. Por favor, descarga una nueva plantilla.");
                    return $this->redirect(['index']);
                }*/

                // Obtener centros de consumo del usuario para mapear las columnas
                $businessData = \backend\helpers\RedisKeys::getValue(\backend\helpers\RedisKeys::BUSINESS_KEY);
                $businessId = $businessData['id'] ?? null;
                $consumptionCenters = \common\models\ConsumptionCenter::find()
                    ->where(['business_id' => $businessId])
                    ->orderBy(['id' => SORT_ASC])
                    ->all();

                // Leer insumos desde la fila 6 en adelante (se agregó fila de alerta)
                $row = 6;
                $errores = [];
                $guardados = 0;
                // Usar la fecha de importación enviada por el usuario o la del servidor como fallback
                $dateEnd = Yii::$app->request->post('fecha_importacion', date('Y-m-d H:i:s'));
                
                while (true) {
                    $insumoNombre = trim($sheet->getCell("A$row")->getValue());
                    if ($insumoNombre === null || $insumoNombre === '') {
                        break;
                    }
                    $unidad = $sheet->getCell("B$row")->getValue();
                    $categoria = $sheet->getCell("C$row")->getValue();

                    // Buscar el insumo por nombre, unidad y categoría
                    $insumo = \common\models\IngredientStock::find()
                        ->where([
                            'ingredient_stock.business_id' => $businessId,
                            'ingredient_stock.ingredient' => $insumoNombre,
                            'ingredient_stock.portion_um' => $unidad
                        ])
                        ->joinWith('category')
                        ->andWhere(['category.name' => $categoria])
                        ->one();
                    if (!$insumo) {
                        $errores[] = "No se encontró el insumo: $insumoNombre ($unidad, $categoria)";
                        $row++;
                        continue;
                    }

                    // Leer existencias para cada centro de consumo dinámicamente y validar que sean numéricos
                    $inventariosData = [];
                    $col = 4; // Empezar desde la columna D
                    $hasInvalidValue = false;
                    foreach ($consumptionCenters as $center) {
                        $existencia = trim($sheet->getCellByColumnAndRow($col, $row)->getValue());
                        if ($existencia !== '' && !is_numeric($existencia)) {
                            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                            $errores[] = "Valor no numérico en fila $row, columna $columnLetter para $insumoNombre: '$existencia'. Solo se permiten números.";
                            $hasInvalidValue = true;
                        }
                        $inventariosData[$center->id] = $existencia === '' ? 0 : $existencia;
                        $col++;
                    }
                    
                    // Si hay valores inválidos en esta fila, saltar al siguiente insumo
                    if ($hasInvalidValue) {
                        $row++;
                        continue;
                    }

                    // Si es edición, eliminar registros existentes para este insumo y fecha
                    $existingInventories = \common\models\Inventory::find()
                        ->where([
                            'ingredient_stock_id' => $insumo->id,
                            'fecha' => $fecha,
                            'business_id' => $businessId
                        ])
                        ->all();
                    
                    foreach ($existingInventories as $existingInv) {
                        \common\models\InventoryConsumptionCenter::deleteAll(['inventory_id' => $existingInv->id]);
                        $existingInv->delete();
                    }

                    // Crear nuevo registro de inventario
                    $inv = new \common\models\Inventory();
                    $inv->ingredient_stock_id = $insumo->id;
                    $inv->business_id = $businessId;
                    $inv->fecha = $fecha;
                    $inv->date_end = $dateEnd;
                    
                    if ($inv->save()) {
                        // Guardar las cantidades por centro de consumo
                        foreach ($consumptionCenters as $center) {
                            $quantity = $inventariosData[$center->id] ?? 0;
                            $icc = new \common\models\InventoryConsumptionCenter();
                            $icc->inventory_id = $inv->id;
                            $icc->consumption_center_id = $center->id;
                            $icc->quantity = $quantity;
                            if (!$icc->save()) {
                                $errores[] = "Error al guardar cantidad para {$center->name} en $insumoNombre: " . json_encode($icc->getErrors());
                            }
                        }
                        $guardados++;
                    } else {
                        $errores[] = "Error al guardar $insumoNombre: " . json_encode($inv->getErrors());
                    }
                    $row++;
                }

                if ($guardados > 0) {
                    Yii::$app->session->setFlash('success', "Inventario importado correctamente. Insumos guardados: $guardados");
                }
                if (!empty($errores)) {
                    Yii::$app->session->setFlash('error', implode('<br>', $errores));
                }
                return $this->redirect(['index']);
            }
        }
        return $this->redirect(['index']);
    }

}
