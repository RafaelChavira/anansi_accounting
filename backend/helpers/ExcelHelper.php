<?php

namespace backend\helpers;

use common\models\Business;
use common\models\Category;
use common\models\Ingredient;
use common\models\Convoy;
use common\models\IngredientStock;
use common\models\IngredientStandardRecipe;
use common\models\RecipeCategory;
use common\models\StandardRecipe;
use common\models\Movement;
use common\models\Provider;
use common\models\StockPrice;
use common\models\UnitOfMeasurement;
use common\models\ConsumptionCenter;
use Yii;
use PhpOffice\PhpSpreadsheet\Calculation\DateTimeExcel\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Yaml\Yaml;
use yii\web\HttpException;

class ExcelHelper
{
    public static function generateReferenceTemplate(Business $business)
    {
        $categories = Category::find()
            ->where([
            'or',
            ['business_id' => $business->id],
            ['business_id' => null],
            ['builtin' => 1],
            ])
            ->all();

        $unitOfMeasurements = UnitOfMeasurement::find()
            ->where(['business_id' => $business->id])
            ->all();

        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
    $activeWorksheet->setCellValue('A1', 'Identificador');
    $activeWorksheet->setCellValue('B1', 'Familias');
    $activeWorksheet->setCellValue('E1', 'Unidad de Medida');
    //$activeWorksheet->setCellValue('F1', 'Tipo');
    // Columnas para flags de UnitOfMeasurement
    $activeWorksheet->setCellValue('F1', "Insumos\n(Compra)");
    $activeWorksheet->getStyle('F1')->getAlignment()->setWrapText(true);
    $activeWorksheet->getStyle('F1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activeWorksheet->getStyle('F1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    // Ajusta la altura de la fila si hace falta
    $activeWorksheet->getRowDimension(1)->setRowHeight(30);
    $activeWorksheet->setCellValue('G1', "Insumos\n(Uso)");
    $activeWorksheet->setCellValue('H1', "Subreceta\n(Rendimiento)");
    $activeWorksheet->setCellValue('I1', "Subreceta\n(UM)");
    $activeWorksheet->setCellValue('J1', "Recetas\n(Rendimiento)");
    $activeWorksheet->setCellValue('K1', "Recetas\n(UM Final)");

    // Aplicar wrap y centrado a las cabeceras G1:K1
    $flagCols = ['G','H','I','J','K'];
    foreach ($flagCols as $col) {
        $activeWorksheet->getStyle($col . '1')->getAlignment()->setWrapText(true);
        $activeWorksheet->getStyle($col . '1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $activeWorksheet->getStyle($col . '1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    }
    // Ajustar la altura de la fila 1 si hace falta
    $activeWorksheet->getRowDimension(1)->setRowHeight(30);

        $currentIndex = 2;
        foreach ($categories as $category) {
            $activeWorksheet->setCellValue("A$currentIndex", $category->key_prefix);
            $activeWorksheet->setCellValue("B$currentIndex", $category->name);
            $currentIndex++;
        }

    $spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
    $spreadsheet->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
    // Ajustar anchuras para las columnas de flags
    $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(18);
    $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(18);
    $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(18);
    $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(18);
    $spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(18);
    $spreadsheet->getActiveSheet()->getColumnDimension('L')->setWidth(18);

        $currentIndex = 2;
        foreach ($unitOfMeasurements as $unitOfMeasurement) {
            $activeWorksheet->setCellValue("E$currentIndex", $unitOfMeasurement->name);
            //$activeWorksheet->setCellValue("F$currentIndex", $unitOfMeasurement->type == 'purchase' ? 'Compra' : ($unitOfMeasurement->type == 'kitchen' ? 'Cocina' : $unitOfMeasurement->type));
            // Marcar con casilla si es true/false
            $activeWorksheet->setCellValue("F$currentIndex", $unitOfMeasurement->is_purchase ? '☑' : '☐');
            $activeWorksheet->setCellValue("G$currentIndex", $unitOfMeasurement->is_kitchen ? '☑' : '☐');
            $activeWorksheet->setCellValue("H$currentIndex", $unitOfMeasurement->is_subrecipe_yield ? '☑' : '☐');
            $activeWorksheet->setCellValue("I$currentIndex", $unitOfMeasurement->is_subrecipe_um ? '☑' : '☐');
            $activeWorksheet->setCellValue("J$currentIndex", $unitOfMeasurement->is_recipe_yield ? '☑' : '☐');
            $activeWorksheet->setCellValue("K$currentIndex", $unitOfMeasurement->is_recipe_final_um ? '☑' : '☐');
            $currentIndex++;
        }

        // Formato condicional: mostrar casillas marcadas en verde y desmarcadas en rojo
        $umLastRow = $currentIndex - 1;
        $flagColumns = ['F','G','H','I','J','K'];
        foreach ($flagColumns as $col) {
            $green = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $green->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
            $green->addCondition('$' . $col . '2="☑"');
            $green->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $green->getStyle()->getFill()->getStartColor()->setRGB('C6EFCE'); // light green
            $green->getStyle()->getFont()->getColor()->setRGB('006100'); // dark green text

            $red = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $red->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
            $red->addCondition('$' . $col . '2="☐"');
            $red->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $red->getStyle()->getFill()->getStartColor()->setRGB('FFC7CE'); // light red
            $red->getStyle()->getFont()->getColor()->setRGB('9C0006'); // dark red text

            $spreadsheet->getActiveSheet()->getStyle($col . '2:' . $col . $umLastRow)->setConditionalStyles([$green, $red]);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Documento_de_referencias.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        $writer->save('php://output');
        exit(200);
    }

    /**
     * Genera plantilla de Proveedores. Si $providers se pasa como array, llena con datos.
     * Mantiene formato similar a otras plantillas en ExcelHelper.
     * @param Business $business
     * @param array|null $providers Array de modelos Provider opcional
     */
    public static function generateProvidersTemplate(Business $business, $providers = null)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Proveedores');

        // Títulos en español; incluir 'Nombre del negocio' como primera columna
        // Agregamos columnas individuales para cada método de pago con su descripción entre paréntesis
        $headers = [
            'Nombre del negocio',
            'RFC',
            'Nombre del contacto',
            'Correo electrónico',
            'Teléfono',
            'Teléfono (contacto)',
            'Dirección',
            'Cuenta/Referencia',
            'Días de crédito',
            'Ventajas',
            'Desventajas',
            'Observaciones',
            // Columnas de métodos de pago con descripción
            'Método de pago 1 (Efectivo)',
            'Método de pago 2 (Transferencia Bancaria)',
            'Método de pago 3 (Cheque)',
            'Método de pago 4 (Tarjeta de Crédito)',
            'Método de pago 5 (Tarjeta de Débito)',
            'Método de pago 6 (Otro método)'
        ];

        // Escribir cabeceras
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            // centrar cabecera (se reforzará con el estilo global más abajo)
            $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $col++;
        }

        // Estilo centrado reutilizable para cabeceras y celdas de datos
        $centerStyle = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];

        // Aplicar centrado a un rango suficientemente grande para la plantilla/exports
        // A1:S500 cubre cabeceras y filas de datos esperadas (ampliado para incluir columnas de pago)
        $sheet->getStyle('A1:S500')->applyFromArray($centerStyle);

    // Definir mapeo de métodos de pago
    $paymentMapping = [
        'cash'        => Yii::t('app', 'Efectivo'),
        'transfer'    => Yii::t('app', 'Transferencia Bancaria'),
        'check'       => Yii::t('app', 'Cheque'),
        'credit_card' => Yii::t('app', 'Tarjeta de Crédito'),
        'debit_card'  => Yii::t('app', 'Tarjeta de Débito'),
        'other'       => Yii::t('app', 'Otro Método')
    ];

    // Configurar validación con dropdown SI para las columnas de métodos de pago (M a R)
    // Las validaciones comienzan en la fila 2 (datos empiezan ahí)
    // Columnas: M=Efectivo, N=Transferencia, O=Cheque, P=Tarjeta Crédito, Q=Tarjeta Débito, R=Otro
    $paymentColumns = ['M', 'N', 'O', 'P', 'Q', 'R'];
    foreach ($paymentColumns as $col) {
        $validation = $sheet->getCell($col . '2')->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true); // permitir SI, NO o dejar en blanco
        $validation->setShowDropDown(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setPromptTitle('Marcar método de pago');
        $validation->setPrompt('Selecciona "SI", "NO" o deja la celda en blanco');
        $validation->setErrorTitle('Valor inválido');
        $validation->setError('Solo puedes seleccionar "SI", "NO" o dejar la celda vacía');
        $validation->setFormula1('"SI,NO"');
        
        // Aplicar validación desde la fila 2 hasta 500
        for ($i = 2; $i <= 500; $i++) {
            $sheet->getCell($col . $i)->setDataValidation(clone $validation);
        }
    }

        // Anchuras - ahora incluye las nuevas columnas de métodos de pago
    foreach (range('A', 'R') as $c) {
            $spreadsheet->getActiveSheet()->getColumnDimension($c)->setAutoSize(true);
        }

        // Si se pasaron providers, escribirlos (comienzan en fila 2)
        if (is_array($providers) && count($providers) > 0) {
            $row = 2;
            foreach ($providers as $p) {
                $col = 'A';
                
                // Determinar qué métodos de pago tiene este proveedor
                $providerMethods = is_array($p->payment_method) ? $p->payment_method : explode(',', $p->payment_method ?? '');
                
                $vals = [
                    $p->business_name,
                    $p->rfc,
                    $p->name,
                    $p->email,
                    $p->phone,
                    $p->second_phone,
                    $p->address,
                    $p->account,
                    $p->credit_days,
                    $p->advantages,
                    $p->disadvantages,
                    $p->observations,
                    // Marcar con "SI" los métodos de pago que tiene el proveedor
                    in_array('cash', $providerMethods) ? 'SI' : '',
                    in_array('transfer', $providerMethods) ? 'SI' : '',
                    in_array('check', $providerMethods) ? 'SI' : '',
                    in_array('credit_card', $providerMethods) ? 'SI' : '',
                    in_array('debit_card', $providerMethods) ? 'SI' : '',
                    in_array('other', $providerMethods) ? 'SI' : '',
                ];
                foreach ($vals as $v) {
                    $sheet->setCellValue($col . $row, $v);
                    $col++;
                }
                $row++;
            }
        }

    $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);
    $fileName = (is_array($providers) && count($providers) > 0) ? 'proveedores_export_' . date('Ymd_His') . '.xlsx' : 'plantilla_proveedores.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        $writer->save('php://output');
        exit(200);
    }

        /**
         * Import providers from an Excel file.
         * Returns an array with keys: 'created' => int, 'errors' => array(row => errors)
         * Mapping supports Spanish headers produced by generateProvidersTemplate and plain attribute names.
         */
        public static function importProviders(Business $business, $fileName)
        {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileName);

            $created = 0;
            $errors = [];

            // mapping labels -> codes used in the views/templates
            $paymentMap = [
                Yii::t('app', 'Efectivo') => 'cash',
                Yii::t('app', 'Transferencia Bancaria') => 'transfer',
                Yii::t('app', 'Cheque') => 'check',
                Yii::t('app', 'Tarjeta de Crédito') => 'credit_card',
                Yii::t('app', 'Tarjeta de Débito') => 'debit_card',
                Yii::t('app', 'Otro Método') => 'other',
                // also accept internal codes directly
                'cash' => 'cash', 'transfer' => 'transfer', 'check' => 'check', 'credit_card' => 'credit_card', 'debit_card' => 'debit_card', 'other' => 'other'
            ];

            $rowIterator = $spreadsheet->getActiveSheet()->getRowIterator();
            // iterate rows like importIngredients pattern
            foreach ($rowIterator as $row) {
                $rowIndex = $row->getRowIndex();
                // Saltar solo fila 1 (encabezados)
                if ($rowIndex == 1) continue;

                $cellIterator = $row->getCellIterator('A', 'R'); // Ampliado a R para incluir columnas de métodos de pago
                $cellIterator->setIterateOnlyExistingCells(false);

                // read first cell - business name - if empty, assume end of data
                $cellIterator->rewind();
                $firstCell = $cellIterator->current();
                $businessNameVal = trim((string)self::resolveCellValue($firstCell));
                if ($businessNameVal === '') {
                    // treat as end of data
                    break;
                }

                // sequentially read columns based on template order
                $data = [];
                // A - Nombre del negocio
                $data['business_name'] = $businessNameVal;
                $cellIterator->next();
                // B - RFC
                $data['rfc'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                // C - Nombre del contacto
                $data['name'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                // D - Correo electrónico
                $data['email'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                // E - Teléfono
                $data['phone'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                // F - Teléfono (contacto)
                $data['second_phone'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                // G - Dirección
                $data['address'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                // H - Cuenta/Referencia (la columna de método de pago ya no existe aquí)
                $data['account'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                // I - Días de crédito
                $data['credit_days'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                // J - Ventajas
                $data['advantages'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                // K - Desventajas
                $data['disadvantages'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                // L - Observaciones
                $data['observations'] = trim((string)self::resolveCellValue($cellIterator->current()));
                $cellIterator->next();
                
                // M-R: Leer columnas de métodos de pago (checkboxes)
                // M - Efectivo
                $cashCheck = trim(strtoupper((string)self::resolveCellValue($cellIterator->current())));
                $cellIterator->next();
                // N - Transferencia Bancaria
                $transferCheck = trim(strtoupper((string)self::resolveCellValue($cellIterator->current())));
                $cellIterator->next();
                // O - Cheque
                $checkCheck = trim(strtoupper((string)self::resolveCellValue($cellIterator->current())));
                $cellIterator->next();
                // P - Tarjeta de Crédito
                $creditCardCheck = trim(strtoupper((string)self::resolveCellValue($cellIterator->current())));
                $cellIterator->next();
                // Q - Tarjeta de Débito
                $debitCardCheck = trim(strtoupper((string)self::resolveCellValue($cellIterator->current())));
                $cellIterator->next();
                // R - Otro Método
                $otherCheck = trim(strtoupper((string)self::resolveCellValue($cellIterator->current())));

                $data['business_id'] = $business->id;

                // create model and normalize payment_method
                $model = new \common\models\Provider(['business_id' => $business->id]);
                $model->load($data, '');

                // Construir array de métodos de pago basado en las columnas marcadas
                $methods = [];
                if ($cashCheck === 'SI') $methods[] = 'cash';
                if ($transferCheck === 'SI') $methods[] = 'transfer';
                if ($checkCheck === 'SI') $methods[] = 'check';
                if ($creditCardCheck === 'SI') $methods[] = 'credit_card';
                if ($debitCardCheck === 'SI') $methods[] = 'debit_card';
                if ($otherCheck === 'SI') $methods[] = 'other';
                
                // If no method provided, set default 'other' to satisfy validation
                if (empty($methods)) $methods[] = 'other';
                $model->payment_method = $methods;

                // Validate and save inside a transaction
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    if ($model->validate()) {
                        if ($model->save(false)) {
                            $transaction->commit();
                            $created++;
                        } else {
                            $transaction->rollBack();
                            $errors[$rowIndex] = $model->getErrors() ?: ['save' => ['No se pudo guardar el proveedor']];
                        }
                    } else {
                        $transaction->rollBack();
                        $errors[$rowIndex] = $model->getErrors();
                    }
                } catch (\Exception $e) {
                    $transaction->rollBack();
                    $errors[$rowIndex] = ['exception' => [$e->getMessage()]];
                }
            }

            return ['created' => $created, 'errors' => $errors];
        }

public static function generateIngredientsTemplate($id)
{
    /** @var Category[] $categories */
    $categories = Category::find()
        ->where([
            'or',
            ['business_id' => $id],
            ['business_id' => null],
        ])
        ->orderBy(['key_prefix' => SORT_ASC])
        ->all();
    /** @var UnitOfMeasurement[] $unitOfMeasurements */
    $unitOfMeasurements = UnitOfMeasurement::find()
        ->where(['business_id' => $id])
        ->all();
    // Separar unidades de compra y de cocina
    $purchaseUMs = array_filter($unitOfMeasurements, function($um) { return $um->is_purchase; });
    $kitchenUMs = array_filter($unitOfMeasurements, function($um) { return $um->is_kitchen; });

    $spreadsheet = new Spreadsheet();
    $activeWorksheet = $spreadsheet->getActiveSheet();
    
    // Cambiar el nombre de la primera hoja a "Catálogo de insumos"
    $activeWorksheet->setTitle('Catálogo de insumos');

    // 7. Configurar estilos
     $centerStyle = [
         'alignment' => [
             'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
             'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
         ],
     ];
    $activeWorksheet->setCellValue("A1", "Clave*");
    $activeWorksheet->setCellValue("B1", "Insumo*");
    $activeWorksheet->setCellValue("C1", "Marca");
    $activeWorksheet->setCellValue("D1", "Presentación");
    // CAMBIO: Cambiar "Categoría*" por "Familia de insumos*"
    $activeWorksheet->setCellValue("E1", "Familia de insumos*");
    $activeWorksheet->setCellValue("F1", "Unidad de compra*");
    $activeWorksheet->setCellValue("G1", "Unidad de uso*");
    $activeWorksheet->setCellValue("H1", "Factor de Rendimiento*");
    $activeWorksheet->setCellValue("I1", "Equivalencia*");
    $activeWorksheet->setCellValue("J1", "Precio*");
    $activeWorksheet->setCellValue("K1", "Observaciones");
    
    $activeWorksheet->getStyle('A1:L500')->applyFromArray($centerStyle);
    $activeWorksheet->freezePane('C2');

    // Set manual column widths instead of auto-size
    $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(15); // Clave
    $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(35); // Insumo
    $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(35); // Insumo
    $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(35); // Insumo
    $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(18); // Familia de insumos
    $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(18); // Unidad de compra
    $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(18); // Unidad de cocina
    $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(25); // Factor de Rendimiento
    $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(25); // Porciones por unidad
    $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(10); // Observaciones
    $spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(30);

    // Create a named range for categories
    $categorySheet = $spreadsheet->createSheet();
    // CAMBIO: Cambiar nombre de la hoja a "Familias de insumos"
    $categorySheet->setTitle('Familias de insumos');
    // CAMBIO: Cambiar texto de cabecera a "Familias de insumos"
    $categorySheet->setCellValue('A1', 'Familias de insumos');

    $row = 2;
    foreach ($categories as $category) {
        $categorySheet->setCellValue("A$row", $category->name);
        $row++;
    }

    // Asegurarse de que el rango nombrado se crea correctamente
    $lastRow = $row - 1;
    $spreadsheet->addNamedRange(
        // CAMBIO: Cambiar nombre del rango a "FamiliasDeInsumos"
        new \PhpOffice\PhpSpreadsheet\NamedRange(
            'FamiliasDeInsumos', 
            $categorySheet, 
            'A2:A' . $lastRow
        )
    );

    // Apply data validation to the category column
    $dataValidation = $spreadsheet->getActiveSheet()->getCell('E2')->getDataValidation();
    $dataValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
    $dataValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
    $dataValidation->setAllowBlank(false);
    $dataValidation->setShowInputMessage(true);
    $dataValidation->setShowErrorMessage(true);
    $dataValidation->setShowDropDown(true);
    $dataValidation->setErrorTitle('Error de entrada');
    $dataValidation->setError('Este valor no es admitido');
    // CAMBIO: Cambiar texto para que coincida con "familias de insumos"
    $dataValidation->setPromptTitle('Selecciona una familia de insumos');
    $dataValidation->setPrompt('Por favor, selecciona un valor del desplegable.');
    
    // CORRECCIÓN: Usar la referencia correcta al rango nombrado
    // Importante: usar el nombre de la hoja entre comillas simples y el rango correcto
    $dataValidation->setFormula1("='Familias de insumos'!\$A\$2:\$A\$$lastRow");

    for ($i = 2; $i <= 5000; $i++) {
        $spreadsheet->getActiveSheet()->getCell("E$i")->setDataValidation(clone $dataValidation);
    }

    // Crear hoja UMs con dos listas: compra y cocina
    $umSheet = $spreadsheet->createSheet();
    $umSheet->setTitle('UMs');
    $umSheet->setCellValue('A1', 'Unidades de compra');
    $umSheet->setCellValue('B1', 'Unidades de cocina');
    $rowCompra = 2;
    foreach ($purchaseUMs as $um) {
        $umSheet->setCellValue("A$rowCompra", $um->name);
        $rowCompra++;
    }
    $rowCocina = 2;
    foreach ($kitchenUMs as $um) {
        $umSheet->setCellValue("B$rowCocina", $um->name);
        $rowCocina++;
    }
    $lastRowCompra = $rowCompra - 1;
    $lastRowCocina = $rowCocina - 1;
    // Validación para unidad de compra (columna F)
    $dataValidationCompra = $spreadsheet->getActiveSheet()->getCell('F2')->getDataValidation();
    $dataValidationCompra->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
    $dataValidationCompra->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
    $dataValidationCompra->setAllowBlank(false);
    $dataValidationCompra->setShowInputMessage(true);
    $dataValidationCompra->setShowErrorMessage(true);
    $dataValidationCompra->setShowDropDown(true);
    $dataValidationCompra->setErrorTitle('Error de entrada');
    $dataValidationCompra->setError('Este valor no es admitido');
    $dataValidationCompra->setPromptTitle('Selecciona una unidad de compra');
    $dataValidationCompra->setPrompt('Por favor, selecciona un valor del desplegable.');
    $dataValidationCompra->setFormula1("='UMs'!\$A\$2:\$A\$" . $lastRowCompra);

    // Validación para unidad de cocina (columna G)
    $dataValidationCocina = $spreadsheet->getActiveSheet()->getCell('G2')->getDataValidation();
    $dataValidationCocina->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
    $dataValidationCocina->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
    $dataValidationCocina->setAllowBlank(false);
    $dataValidationCocina->setShowInputMessage(true);
    $dataValidationCocina->setShowErrorMessage(true);
    $dataValidationCocina->setShowDropDown(true);
    $dataValidationCocina->setErrorTitle('Error de entrada');
    $dataValidationCocina->setError('Este valor no es admitido');
    $dataValidationCocina->setPromptTitle('Selecciona una unidad de cocina');
    $dataValidationCocina->setPrompt('Por favor, selecciona un valor del desplegable.');
    $dataValidationCocina->setFormula1("='UMs'!\$B\$2:\$B\$" . $lastRowCocina);

    for ($i = 2; $i <= 5000; $i++) {
        $spreadsheet->getActiveSheet()->getCell("F$i")->setDataValidation(clone $dataValidationCompra);
        $spreadsheet->getActiveSheet()->getCell("G$i")->setDataValidation(clone $dataValidationCocina);
    }

    // Apply data validation to the factor de rendimiento column
    $factorValidation = $spreadsheet->getActiveSheet()->getCell('H2')->getDataValidation();
    $factorValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
    $factorValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
    $factorValidation->setAllowBlank(false);
    $factorValidation->setShowInputMessage(true);
    $factorValidation->setShowErrorMessage(true);
    $factorValidation->setErrorTitle('Error de entrada');
    $factorValidation->setError('Este valor no es admitido');
    $factorValidation->setPromptTitle('Factor de Rendimiento');
    $factorValidation->setPrompt('Por favor, ingresa un valor numérico entre 0 y 100 (permite decimales)');
    $factorValidation->setFormula1(0); // Valor mínimo
    $factorValidation->setFormula2(100); // Valor máximo

    for ($i = 2; $i <= 5000; $i++) {
        $spreadsheet->getActiveSheet()->getCell("H$i")->setDataValidation(clone $factorValidation);
    }

    // Apply data validation to the price column - Permitir cualquier valor numérico
    $priceValidation = $spreadsheet->getActiveSheet()->getCell('J2')->getDataValidation();
    $priceValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
    $priceValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
    $priceValidation->setAllowBlank(false);
    $priceValidation->setShowInputMessage(true);
    $priceValidation->setShowErrorMessage(true);
    $priceValidation->setErrorTitle('Error de entrada');
    $priceValidation->setError('Este valor no es admitido');
    $priceValidation->setPromptTitle('Precio');
    $priceValidation->setPrompt('Por favor, ingresa un valor numérico.');
    $priceValidation->setFormula1(0);
    $priceValidation->setFormula2(99999999); // Aumentar el límite máximo

    for ($i = 2; $i <= 5000; $i++) {
        $spreadsheet->getActiveSheet()->getCell("J$i")->setDataValidation(clone $priceValidation);
    }
     // Validación para EQ. UNI. Cocina (columna I): solo números
    $eqValidation = $spreadsheet->getActiveSheet()->getCell('I2')->getDataValidation();
    $eqValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
    $eqValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
    $eqValidation->setAllowBlank(false);
    $eqValidation->setShowInputMessage(true);
    $eqValidation->setShowErrorMessage(true);
    $eqValidation->setErrorTitle('Error de entrada');
    $eqValidation->setError('Solo se permiten valores numéricos.');
    $eqValidation->setPromptTitle('Equivalencia unidad de cocina');
    $eqValidation->setPrompt('Ingresa solo números (ejemplo: 12, 0.5, 100).');
    $eqValidation->setFormula1(0); // Valor mínimo
    $eqValidation->setFormula2(99999999); // Valor máximo
    for ($i = 2; $i <= 5000; $i++) {
        $spreadsheet->getActiveSheet()->getCell("I$i")->setDataValidation(clone $eqValidation);
    }

    // Create a legend sheet
    $legendSheet = $spreadsheet->createSheet();
    $legendSheet->setTitle('Instrucciones');
    $legendSheet->setCellValue('A1', 'Columna');
    $legendSheet->setCellValue('B1', 'Descripción');
    $legendSheet->setCellValue('A2', 'Clave');
    $legendSheet->setCellValue('B2', 'Debe ser un número entero. No puede contener % ni $.');
    // CAMBIO: Actualizar textos en la leyenda para referirse a "familias de insumos"
    $legendSheet->setCellValue('A3', 'Familia de insumos');
    $legendSheet->setCellValue('B3', 'Debe ser una de las familias de insumos listadas');
    $legendSheet->setCellValue('A4', 'Unidad de Medida');
    $legendSheet->setCellValue('B4', 'Debe ser una de las unidades de medida listadas');
    $legendSheet->setCellValue('A5', 'Factor de Rendimiento');
    $legendSheet->setCellValue('B5', 'Debe ser un valor numérico entre 0 y 100. Permite decimales.');
    $legendSheet->setCellValue('A6', 'EQ. UNI. Cocina');
    $legendSheet->setCellValue('B6', 'Equivalencia entre la unidad de compra y la unidad de cocina. Es decir, cuántas unidades de cocina hay en una unidad de compra. Ejemplo: Si compras 1 pieza y esa pieza equivale a 2 kg, escribe 2. Solo se permiten números.');
    $legendSheet->setCellValue('A7', 'Precio');
    $legendSheet->setCellValue('B7', 'Debe ser un valor numérico mayor que 0.');

    //$spreadsheet->getSheetByName('Leyenda')->getColumnDimension('A')->setAutoSize(true);
    //$spreadsheet->getSheetByName('Leyenda')->getColumnDimension('B')->setAutoSize(true);

    // Asegurarse de que las hojas ocultas (Familias de insumos y UMs) no sean visibles
    $categorySheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
    $umSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

    // Activar la primera hoja antes de guardar
    $spreadsheet->setActiveSheetIndex(0);
    
    // Establecer la celda activa en A2 para que el usuario pueda empezar a llenar datos inmediatamente
    $spreadsheet->getActiveSheet()->setSelectedCell('A2');
      // Proteger la fila de títulos (A1:K1) para que no se puedan editar
    $protection = $activeWorksheet->getProtection();
    $protection->setSheet(true);
    // $protection->setPassword(''); // Sin clave
    // Desbloquear todas las celdas primero
    $activeWorksheet->getStyle('A2:K5000')->getProtection()->setLocked(
        \PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED
    );
    // Bloquear solo la fila de títulos
    $activeWorksheet->getStyle('A1:K1')->getProtection()->setLocked(
        \PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_PROTECTED
    );

    $writer = new Xlsx($spreadsheet);
    $fileName = 'Plantilla_para_importar_insumos.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
    $writer->save('php://output');
    exit(200);
}
public static function generateMovementTemplate($businessId = null)
{
    $spreadsheet = new Spreadsheet();
    $activeWorksheet = $spreadsheet->getActiveSheet();
    $activeWorksheet->setTitle('Movimientos');

    // Configurar estilos
    $centerStyle = [
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
    ];

    // Configurar bordes ANTES de cualquier otro estilo para no sobrescribir RichText
    $borderStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'E0E0E0']
            ]
        ]
    ];
    $activeWorksheet->getStyle('A1:N500')->applyFromArray($borderStyle);

    // Aplicar estilo centrado ANTES de configurar RichText para no sobrescribir
    $activeWorksheet->getStyle('A1:N1')->applyFromArray($centerStyle);

    // Encabezados en texto plano con indicadores: [T]=Todos, [E]=Entrada, [S]=Salida
    $activeWorksheet->setCellValue("A1", "Movimiento [T]");
    $activeWorksheet->setCellValue("B1", "Fecha (año-mes-dia) [T]");
    $activeWorksheet->setCellValue("C1", "Insumo [T]");
    $activeWorksheet->setCellValue("D1", "Clave");
    $activeWorksheet->setCellValue("E1", "Proveedor [E]");
    $activeWorksheet->setCellValue("F1", "Tipo de Pago [E]");
    $activeWorksheet->setCellValue("G1", "Factura");
    $activeWorksheet->setCellValue("H1", "Centro de Consumo [S]");
    $activeWorksheet->setCellValue("I1", "Cantidad [T]");
    $activeWorksheet->setCellValue("J1", "Precio de Compra [E]");
    $activeWorksheet->setCellValue("K1", "Impuesto");
    $activeWorksheet->setCellValue("L1", "Precio Unitario [E]");
    $activeWorksheet->setCellValue("M1", "Total [E]");
    $activeWorksheet->setCellValue("N1", "Observaciones");

    // Agregar comentarios descriptivos a los encabezados
    $comment = $activeWorksheet->getComment('A1');
    $textRun = $comment->getText()->createTextRun('Selecciona si el movimiento es una Entrada (compra o ingreso al almacén) o una Salida (requisición, consumo, merma o traspaso).');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('100px');

    $comment = $activeWorksheet->getComment('D1');
    $textRun = $comment->getText()->createTextRun('(Opcional) Clave única del insumo. Si ya está registrado en el sistema, se llenará automáticamente al seleccionarla. Si no lo conoces, déjalo en blanco.');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('C1');
    $textRun = $comment->getText()->createTextRun('Selecciona un insumo de la lista desplegable. También se completará automáticamente al seleccionar una clave.');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('B1');
    $textRun = $comment->getText()->createTextRun('Fecha en que se realizó la entrada o salida del insumo. Usa el formato AAAA-MM-DD (ej. 2025-08-06).');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('E1');
    $textRun = $comment->getText()->createTextRun('Nombre del proveedor o empresa a quien se compró el insumo. Si no tienes proveedor fijo, escribe o selecciona "Por definir".');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('F1');
    $textRun = $comment->getText()->createTextRun('Selecciona la forma en que se realizó el pago. Ejemplos: Efectivo, Transferencia, etc.');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('60px');

    $comment = $activeWorksheet->getComment('G1');
    $textRun = $comment->getText()->createTextRun('(Opcional) Número de factura o comprobante de la compra. Si no aplica, puedes dejarlo en blanco.');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('H1');
    $textRun = $comment->getText()->createTextRun('Solo para SALIDAS: indica en qué área se utilizó el insumo. No llenar si el movimiento es una Entrada.');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('I1');
    $textRun = $comment->getText()->createTextRun('Número de unidades compradas. Debe coincidir con la unidad de compra registrada (kg, litros, piezas, etc.).');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('J1');
    $textRun = $comment->getText()->createTextRun('Precio total pagado por el insumo, antes de impuestos. Este dato se usa para calcular el precio unitario.');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('K1');
    $textRun = $comment->getText()->createTextRun('Monto del impuesto aplicado (ej. IVA). Si no hubo impuestos, deja en blanco o coloca 0.');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('L1');
    $textRun = $comment->getText()->createTextRun('Se calcula automáticamente si no lo llenas. Es el precio por unidad del insumo ((Precio de Compra + Impuesto) ÷ Cantidad).');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('450px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('M1');
    $textRun = $comment->getText()->createTextRun('Total con impuestos. Se usa para validar los datos. Si no lo llenas, el sistema lo calcula automáticamente.');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('400px');
    $comment->setHeight('80px');

    $comment = $activeWorksheet->getComment('N1');
    $textRun = $comment->getText()->createTextRun('(Opcional) Notas adicionales como promociones, aclaraciones, devoluciones, detalles del insumo o cualquier información útil.');
    $textRun->getFont()->setSize(12);
    $comment->setWidth('450px');
    $comment->setHeight('80px');

    // Aplicar estilo centrado a todas las celdas de datos (sin incluir encabezados que ya tienen RichText)
    $activeWorksheet->freezePane('D2');

    // Aplicar estilo centrado a todas las celdas de datos
    $activeWorksheet->getStyle('A2:N500')->applyFromArray($centerStyle);

    // Configurar anchos de columnas
    $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(19); // Movimiento
    $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(15); // Clave
    $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(35); // Insumo
    $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(27); // Fecha
    $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(25); // Proveedor
    $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(25); // Tipo de Pago
    $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(15); // Factura
    $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(26); // Centro de Consumo
    $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(17); // Cantidad
    $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(25); // Precio de Compra
    $spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(15); // Impuesto
    $spreadsheet->getActiveSheet()->getColumnDimension('L')->setWidth(21); // Precio Unitario
    $spreadsheet->getActiveSheet()->getColumnDimension('M')->setWidth(15); // Total
    $spreadsheet->getActiveSheet()->getColumnDimension('N')->setWidth(30); // Observaciones

    // Configurar formato de fecha para la columna B (Fecha)
    $spreadsheet->getActiveSheet()->getStyle('B2:B500')
        ->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD);

    // Hoja de insumos
    if ($businessId) {
        $ingredientsSheet = $spreadsheet->createSheet();
        $ingredientsSheet->setTitle('Insumos');
        $ingredientsSheet->setCellValue('A1', 'Clave');
        $ingredientsSheet->setCellValue('B1', 'Insumo');
        $ingredientsSheet->setCellValue('C1', 'Unidad');
        $ingredientsSheet->setCellValue('D1', 'UnidadCocina');

        $ingredients = IngredientStock::find()
            ->where(['business_id' => $businessId])
            ->orderBy(['ingredient' => SORT_ASC])
            ->all();

        $row = 2;
        foreach ($ingredients as $ingredient) {
            // Concatenar el nombre del insumo con la unidad de medida entre paréntesis
            $ingredientWithUnit = $ingredient->ingredient;
            if (!empty($ingredient->um)) {
                $ingredientWithUnit .= ' (' . $ingredient->um . ')';
            }
            // Crear versión para Salida con unidad porción (portion_um)
            $ingredientWithPortionUnit = $ingredient->ingredient;
            if (!empty($ingredient->portion_um)) {
                $ingredientWithPortionUnit .= ' (' . $ingredient->portion_um . ')';
            } else if (!empty($ingredient->um)) {
                // Si no hay portion_um, usar um como fallback
                $ingredientWithPortionUnit .= ' (' . $ingredient->um . ')';
            }
            $ingredientsSheet->setCellValue("A$row", $ingredient->key);
            $ingredientsSheet->setCellValue("B$row", $ingredientWithUnit);
            $ingredientsSheet->setCellValue("C$row", $ingredient->um);
            $ingredientsSheet->setCellValue("D$row", $ingredientWithPortionUnit);
            $row++;
        }

        $ingredientsSheet->getColumnDimension('A')->setWidth(15);
        $ingredientsSheet->getColumnDimension('B')->setWidth(40);
        $ingredientsSheet->getColumnDimension('C')->setWidth(15);
        $ingredientsSheet->getColumnDimension('D')->setWidth(15);

        // Aplicar validación de datos a las columnas en la hoja principal
        if ($row > 2) {
            $mainSheet = $spreadsheet->getSheet(0); // Obtener la hoja principal

            // Validación para columna A (Movimiento) - desplegable con Entrada/Salida
            $movementValidation = $mainSheet->getCell('A2')->getDataValidation();
            $movementValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $movementValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $movementValidation->setAllowBlank(false);
            $movementValidation->setShowInputMessage(true);
            $movementValidation->setShowErrorMessage(true);
            $movementValidation->setShowDropDown(true);
            $movementValidation->setErrorTitle('Error de entrada');
            $movementValidation->setError('Este valor no es admitido. Debe seleccionar Entrada o Salida.');
            $movementValidation->setPromptTitle('Tipo de Movimiento');
            $movementValidation->setPrompt('Seleccione el tipo de movimiento: Entrada o Salida.');
            $movementValidation->setFormula1('"Entrada,Salida"');

            // Validación para columna B (Fecha) - solo fechas desde 2025 usando validación custom
            $dateValidation = $mainSheet->getCell('B2')->getDataValidation();
            $dateValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_CUSTOM);
            $dateValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $dateValidation->setAllowBlank(true);
            $dateValidation->setShowInputMessage(true);
            $dateValidation->setShowErrorMessage(true);
            $dateValidation->setShowDropDown(false);
            $dateValidation->setErrorTitle('Error de fecha');
            $dateValidation->setError('Solo se permiten fechas desde el 1 de enero de 2025 en adelante. Formato: año-mes-día (ej: 2025-08-19)');
            $dateValidation->setPromptTitle('Fecha del movimiento');
            $dateValidation->setPrompt('Ingrese una fecha desde 2025 en adelante en formato año-mes-día (ej: 2025-08-19)');
            // Fórmula personalizada que verifica que el año sea >= 2025
            $dateValidation->setFormula1('AND(ISNUMBER(B2),YEAR(B2)>=2025)');

            // Validación para columna D (Clave) - desplegable con todas las claves
            $keyValidation = $mainSheet->getCell('D2')->getDataValidation();
            $keyValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $keyValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $keyValidation->setAllowBlank(true); // Permitir vacío
            $keyValidation->setShowInputMessage(true);
            $keyValidation->setShowErrorMessage(true);
            $keyValidation->setShowDropDown(true);
            $keyValidation->setErrorTitle('Error de entrada');
            $keyValidation->setError('Este valor no es admitido. Debe seleccionar una clave válida.');
            $keyValidation->setPromptTitle('Selecciona una clave');
            $keyValidation->setPrompt('Selecciona una clave del desplegable. El insumo se completará automáticamente.');
            $keyValidation->setFormula1('Insumos!$A$2:$A$' . ($row - 1));

            // Validación para columna I (Cantidad) - solo números decimales positivos
            $quantityValidation = $mainSheet->getCell('I2')->getDataValidation();
            $quantityValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
            $quantityValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $quantityValidation->setAllowBlank(true);
            $quantityValidation->setShowInputMessage(true);
            $quantityValidation->setShowErrorMessage(true);
            $quantityValidation->setErrorTitle('Error de entrada');
            $quantityValidation->setError('Solo se permiten números decimales positivos (ej: 5, 2.5, 10.75).');
            $quantityValidation->setPromptTitle('Cantidad');
            $quantityValidation->setPrompt('Ingrese la cantidad como número decimal positivo.');
            $quantityValidation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHAN);
            $quantityValidation->setFormula1(0);

            // Validación para columna J (Precio de Compra) - solo números decimales positivos
            $priceValidation = $mainSheet->getCell('J2')->getDataValidation();
            $priceValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
            $priceValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $priceValidation->setAllowBlank(true);
            $priceValidation->setShowInputMessage(true);
            $priceValidation->setShowErrorMessage(true);
            $priceValidation->setErrorTitle('Error de entrada');
            $priceValidation->setError('Solo se permiten números decimales positivos (ej: 25.50, 100, 15.99).');
            $priceValidation->setPromptTitle('Precio de Compra');
            $priceValidation->setPrompt('Ingrese el precio como número decimal positivo.');
            $priceValidation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHANOREQUAL);
            $priceValidation->setFormula1(0);

            // Validación para columna K (Impuesto) - solo números decimales positivos o cero
            $taxValidation = $mainSheet->getCell('K2')->getDataValidation();
            $taxValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
            $taxValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $taxValidation->setAllowBlank(true);
            $taxValidation->setShowInputMessage(true);
            $taxValidation->setShowErrorMessage(true);
            $taxValidation->setErrorTitle('Error de entrada');
            $taxValidation->setError('Solo se permiten números decimales positivos o cero (ej: 0, 16.5, 25.00).');
            $taxValidation->setPromptTitle('Impuesto');
            $taxValidation->setPrompt('Ingrese el impuesto como número decimal positivo o cero.');
            $taxValidation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHANOREQUAL);
            $taxValidation->setFormula1(0);

            // Aplicar validaciones y fórmulas a todas las filas
            for ($i = 2; $i <= 500; $i++) {
                // Aplicar validación a columna A (Movimiento)
                $mainSheet->getCell("A$i")->setDataValidation(clone $movementValidation);

                // Aplicar validación a columna B (Fecha) - crear validación específica para cada fila
                $rowDateValidation = clone $dateValidation;
                $rowDateValidation->setFormula1("AND(ISNUMBER(B$i),YEAR(B$i)>=2025)");
                $mainSheet->getCell("B$i")->setDataValidation($rowDateValidation);

                // Aplicar validación a columna D (Clave)
                $mainSheet->getCell("D$i")->setDataValidation(clone $keyValidation);

                // Aplicar validación a columna I (Cantidad) - solo números
                $mainSheet->getCell("I$i")->setDataValidation(clone $quantityValidation);

                // Aplicar validación a columna J (Precio de Compra) - solo números
                $mainSheet->getCell("J$i")->setDataValidation(clone $priceValidation);

                // Aplicar validación a columna K (Impuesto) - solo números
                $mainSheet->getCell("K$i")->setDataValidation(clone $taxValidation);

                // Fórmula BUSCARV en columna C (Insumo) - solo autocompleta si A está definido
                $mainSheet->setCellValue("C$i", "=IF(D$i<>\"\",IF(A$i=\"Salida\",VLOOKUP(D$i,Insumos!\$A\$2:\$D\$1000,4,FALSE),IF(A$i=\"Entrada\",VLOOKUP(D$i,Insumos!\$A\$2:\$D\$1000,2,FALSE),\"\")),\"\")");
            }

            // Aplicar formato condicional a la columna D (Clave)
            // Condición: Si C (Insumo) no está vacía Y D (Clave) está vacía, poner en gris
            $conditionalFormatting = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $conditionalFormatting->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
            $conditionalFormatting->addCondition('AND($C2<>"",$D2="")');
            $conditionalFormatting->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $conditionalFormatting->getStyle()->getFill()->getStartColor()->setRGB('D3D3D3'); // Gris claro

            $mainSheet->getStyle('D2:D500')->setConditionalStyles([$conditionalFormatting]);

            // Crear rangos con nombre para ambas columnas
            $ingredientsSheet = $spreadsheet->getSheetByName('Insumos');
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange(
                    'ListaInsumosEntrada',
                    $ingredientsSheet,
                    "'Insumos'!\$B\$2:\$B\$" . ($row - 1)
                )
            );
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange(
                    'ListaInsumosSalida',
                    $ingredientsSheet,
                    "'Insumos'!\$D\$2:\$D\$" . ($row - 1)
                )
            );

            // Dummy no vacío para bloquear C cuando A está vacío.
            // IMPORTANTE: se mantiene con texto no vacío (no cadena "") porque un rango
            // origen con celdas realmente en blanco hace que Excel acepte cualquier valor.
            $mainSheet->setCellValue('Z1', 'SELECCIONE_MOVIMIENTO');
            $mainSheet->setCellValue('Z2', 'SELECCIONE_MOVIMIENTO');
            $mainSheet->getColumnDimension('Z')->setVisible(false);
            $ingredientsSheet->setCellValue('Z1', 'SELECCIONE_MOVIMIENTO');
            $ingredientsSheet->setCellValue('Z2', 'SELECCIONE_MOVIMIENTO');
            $ingredientsSheet->getColumnDimension('Z')->setVisible(false);

            $existingEmpty = $spreadsheet->getNamedRange('ListaVaciaInsumos');
            if ($existingEmpty === null) {
                $spreadsheet->addNamedRange(
                    new \PhpOffice\PhpSpreadsheet\NamedRange(
                        'ListaVaciaInsumos',
                        $ingredientsSheet,
                        "'Insumos'!\$Z\$1:\$Z\$2"
                    )
                );
            }
            $existingEmptyMain = $spreadsheet->getNamedRange('ListaVaciaMain');
            if ($existingEmptyMain === null) {
                $spreadsheet->addNamedRange(
                    new \PhpOffice\PhpSpreadsheet\NamedRange(
                        'ListaVaciaMain',
                        $mainSheet,
                        "'Movimientos'!\$Z\$1:\$Z\$2"
                    )
                );
            }

            // FIX CLAVE: columna auxiliar oculta P que calcula, como TEXTO, el NOMBRE
            // del rango con nombre que corresponde según el tipo de movimiento.
            // Esto es necesario porque un IF(...) que devuelve rangos directamente
            // dentro de Formula1 no siempre bloquea el texto tecleado a mano en Excel;
            // en cambio, INDIRECT(referencia_a_celda_con_el_nombre_del_rango) sí lo hace
            // de forma confiable (mismo patrón que ya usábamos en la columna F).
            for ($i = 2; $i <= 500; $i++) {
                $mainSheet->setCellValue("P$i", "=IF(\$A$i=\"Salida\",\"ListaInsumosSalida\",IF(\$A$i=\"Entrada\",\"ListaInsumosEntrada\",\"ListaVaciaMain\"))");
            }
            $mainSheet->getColumnDimension('P')->setVisible(false);

            // Validación para columna C (Insumo) - desplegable dinámico según tipo de movimiento
            $ingredientValidation = $mainSheet->getCell('C2')->getDataValidation();
            $ingredientValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $ingredientValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $ingredientValidation->setAllowBlank(true);
            $ingredientValidation->setShowInputMessage(true);
            $ingredientValidation->setShowErrorMessage(true);
            $ingredientValidation->setShowDropDown(true);
            $ingredientValidation->setErrorTitle('Error de entrada');
            $ingredientValidation->setError('Este valor no es admitido. Debe seleccionar un insumo válido. Asegúrate de elegir primero el tipo de movimiento (Entrada/Salida) en la columna A.');
            $ingredientValidation->setPromptTitle('Selecciona un insumo');
            $ingredientValidation->setPrompt('Primero selecciona Entrada o Salida en la columna A, luego selecciona un insumo de la lista. También se completa automáticamente al seleccionar una clave.');

            // Aplicar validación dinámica a múltiples filas usando INDIRECT sobre la columna auxiliar P
            for ($i = 2; $i <= 500; $i++) {
                $validation = clone $ingredientValidation;
                $validation->setFormula1("INDIRECT(\$P$i)");
                $mainSheet->getCell("C$i")->setDataValidation($validation);
            }

            // Formato condicional en C para marcar en rojo valores que no pertenecen a la lista correspondiente
            $invalidInsumoConditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $invalidInsumoConditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
            $invalidInsumoConditional->addCondition('OR(AND($C2<>"",$A2=""),AND($C2<>"",$A2="Entrada",ISNA(MATCH($C2,ListaInsumosEntrada,0))),AND($C2<>"",$A2="Salida",ISNA(MATCH($C2,ListaInsumosSalida,0))))');
            $invalidInsumoConditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $invalidInsumoConditional->getStyle()->getFill()->getStartColor()->setRGB('FFC7CE');
            $invalidInsumoConditional->getStyle()->getFont()->getColor()->setRGB('9C0006');
            $mainSheet->getStyle('C2:C500')->setConditionalStyles([$invalidInsumoConditional]);
        }
    }

    // Hoja de proveedores
    if ($businessId) {
        $providersSheet = $spreadsheet->createSheet();
        $providersSheet->setTitle('Proveedores');
        $providersSheet->setCellValue('A1', 'Proveedor');
        $providersSheet->setCellValue('B1', 'Métodos de Pago');

        $providers = Provider::find()
            ->where(['business_id' => $businessId])
            ->all();

        $row = 2;
        $allProviders = [];

        // Agregar siempre "Por definir" como primera opción
        $providersSheet->setCellValue("A$row", "Por definir");
        $providersSheet->setCellValue("B$row", "Todos los métodos disponibles");
        $providersSheet->setCellValue("C$row", "PM_PorDefinir");
        $allProviders[] = "Por definir";
        $row++;

        // Para cada proveedor crear también una hoja con sus métodos de pago
        foreach ($providers as $providerIndex => $provider) {
            $providersSheet->setCellValue("A$row", $provider->business_name);
            $paymentMethods = is_array($provider->payment_method) ?
                $provider->payment_method :
                explode(',', $provider->payment_method);

            $paymentMethodStr = is_array($provider->payment_method) ?
                implode(', ', $provider->payment_method) :
                $provider->payment_method;
            $providersSheet->setCellValue("B$row", $paymentMethodStr);
            $allProviders[] = $provider->business_name;

            // Crear una hoja para los métodos de pago de este proveedor
            $providerName = preg_replace('/[^\w\s]/', '', $provider->business_name);
            $providerName = substr(str_replace(' ', '_', $providerName), 0, 20); // Limitar longitud
            $providerSheetName = 'PM_' . ($providerIndex + 1); // Nombre corto para evitar problemas

            $providerPaymentSheet = $spreadsheet->createSheet();
            $providerPaymentSheet->setTitle($providerSheetName);
            $providerPaymentSheet->setCellValue('A1', 'Valor');
            $providerPaymentSheet->setCellValue('B1', 'Descripción');

            // Mapear códigos de pago a descripciones en español
            $paymentDescriptions = [
                Movement::PAYMENT_METHOD_CASH => 'Efectivo',
                Movement::PAYMENT_METHOD_TRANSFER => 'Transferencia Bancaria',
                Movement::PAYMENT_METHOD_CHECK => 'Cheque',
                Movement::PAYMENT_METHOD_CREDIT_CARD => 'Tarjeta de Crédito',
                Movement::PAYMENT_METHOD_DEBIT_CARD => 'Tarjeta de Débito',
                Movement::PAYMENT_METHOD_OTHER => 'Otro Método de Pago'
            ];

            // Agregar solo los métodos de pago disponibles para este proveedor
            $pmRow = 2;
            foreach ($paymentMethods as $method) {
                $method = trim($method);
                if (!empty($method)) {
                    // Mostrar la descripción en español en lugar del código
                    $description = isset($paymentDescriptions[$method]) ? $paymentDescriptions[$method] : $method;
                    $providerPaymentSheet->setCellValue("A$pmRow", $description);
                    $providerPaymentSheet->setCellValue("B$pmRow", $method); // Código interno para referencia
                    $pmRow++;
                }
            }

            $providerPaymentSheet->getColumnDimension('A')->setWidth(25);
            $providerPaymentSheet->getColumnDimension('B')->setWidth(20);

            // Asociar el nombre de la hoja con el proveedor para uso posterior
            $providersSheet->setCellValue("C$row", $providerSheetName);

            $row++;
        }

        // Crear hoja especial para "Por definir" con todos los tipos de pago
        $porDefinirSheet = $spreadsheet->createSheet();
        $porDefinirSheet->setTitle('PM_PorDefinir');
        $porDefinirSheet->setCellValue('A1', 'Valor');
        $porDefinirSheet->setCellValue('B1', 'Descripción');

        // Mapear códigos de pago a descripciones en español
        $paymentDescriptions = [
            Movement::PAYMENT_METHOD_CASH => 'Efectivo',
            Movement::PAYMENT_METHOD_TRANSFER => 'Transferencia Bancaria',
            Movement::PAYMENT_METHOD_CHECK => 'Cheque',
            Movement::PAYMENT_METHOD_CREDIT_CARD => 'Tarjeta de Crédito',
            Movement::PAYMENT_METHOD_DEBIT_CARD => 'Tarjeta de Débito',
            Movement::PAYMENT_METHOD_OTHER => 'Otro Método de Pago'
        ];

        // Agregar "Por definir" primero
        $porDefinirSheet->setCellValue('A2', 'Por definir');
        $porDefinirSheet->setCellValue('B2', 'Por definir');

        // Agregar todos los métodos de pago disponibles
        $pmRow = 3;
        foreach ($paymentDescriptions as $code => $description) {
            $porDefinirSheet->setCellValue("A$pmRow", $description);
            $porDefinirSheet->setCellValue("B$pmRow", $code);
            $pmRow++;
        }

        $porDefinirSheet->getColumnDimension('A')->setWidth(25);
        $porDefinirSheet->getColumnDimension('B')->setWidth(20);

        $providersSheet->getColumnDimension('A')->setWidth(30);
        $providersSheet->getColumnDimension('B')->setWidth(40);

        // Aplicar validación de datos a la columna de proveedor (ahora columna E) en la hoja principal
        if ($row > 2) {
            $mainSheet = $spreadsheet->getSheet(0); // Obtener la hoja principal
            $providerValidation = $mainSheet->getCell('E2')->getDataValidation();
            $providerValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $providerValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $providerValidation->setAllowBlank(false);
            $providerValidation->setShowInputMessage(true);
            $providerValidation->setShowErrorMessage(true);
            $providerValidation->setShowDropDown(true);
            $providerValidation->setErrorTitle('Error de entrada');
            $providerValidation->setError('Este valor no es admitido. Debe seleccionar un proveedor válido.');
            $providerValidation->setPromptTitle('Selecciona un proveedor');
            $providerValidation->setPrompt('Por favor, selecciona un proveedor de la lista desplegable.');
            $providerValidation->setFormula1('Proveedores!$A$2:$A$' . ($row - 1));

            // Aplicar validación a múltiples filas
            for ($i = 2; $i <= 500; $i++) {
                $mainSheet->getCell("E$i")->setDataValidation(clone $providerValidation);
            }
        }
    }

    // Hoja de centros de consumo
    if ($businessId) {
        $consumptionCentersSheet = $spreadsheet->createSheet();
        $consumptionCentersSheet->setTitle('Centros de Consumo');
        $consumptionCentersSheet->setCellValue('A1', 'Centro de Consumo');

        $consumptionCenters = ConsumptionCenter::find()
            ->where(['business_id' => $businessId])
            ->all();

        $ccRow = 2;
        foreach ($consumptionCenters as $center) {
            $consumptionCentersSheet->setCellValue("A$ccRow", $center->name);
            $ccRow++;
        }

        $consumptionCentersSheet->getColumnDimension('A')->setWidth(30);

        // Aplicar validación de datos a la columna de Centro de Consumo (columna H)
        if ($ccRow > 2) {
            $mainSheet = $spreadsheet->getSheet(0); // Hoja principal
            $ccSheet   = $spreadsheet->getSheetByName('Centros de Consumo'); // Hoja de lista de CC

            // 1️⃣ Crear la lista de centros de consumo como rango con nombre "ListaCC"
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange(
                    'ListaCC',
                    $ccSheet,
                    "'Centros de Consumo'!\$A\$2:\$A\$" . ($ccRow - 1)
                )
            );

            // 2️⃣ Rango dummy "vacío" con texto NO vacío (evita el bug de blancos en lista de origen)
            $ccSheet->setCellValue('B1', 'NA');
            $ccSheet->setCellValue('B2', 'NA');
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange(
                    'ListaVacia',
                    $ccSheet,
                    "'Centros de Consumo'!\$B\$1:\$B\$2"
                )
            );

            // 3️⃣ Configurar validación base (TYPE_LIST)
            $baseValidation = $mainSheet->getCell('H2')->getDataValidation();
            $baseValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $baseValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $baseValidation->setAllowBlank(true);
            $baseValidation->setShowDropDown(true);
            $baseValidation->setShowInputMessage(true);
            $baseValidation->setShowErrorMessage(true);
            $baseValidation->setErrorTitle('Error de entrada');
            $baseValidation->setError('Debe seleccionar un centro de consumo válido de la lista (solo si es SALIDA).');
            $baseValidation->setPromptTitle('Centro de Consumo');
            $baseValidation->setPrompt('Selecciona el centro de consumo solo cuando el movimiento es SALIDA.');

            // 4️⃣ FIX: columna auxiliar oculta Q con el NOMBRE del rango como texto,
            // y validación por INDIRECT (mismo patrón que columna P para Insumo).
            for ($i = 2; $i <= 500; $i++) {
                $mainSheet->setCellValue("Q$i", "=IF(\$A$i=\"Salida\",\"ListaCC\",\"ListaVacia\")");
            }
            $mainSheet->getColumnDimension('Q')->setVisible(false);

            for ($i = 2; $i <= 500; $i++) {
                $validation = clone $baseValidation;
                $validation->setFormula1("INDIRECT(\$Q$i)");
                $mainSheet->getCell("H{$i}")->setDataValidation($validation);
            }
        }
    }

    // Hoja de referencia de tipos de pago
    $paymentTypesSheet = $spreadsheet->createSheet();
    $paymentTypesSheet->setTitle('Tipos de Pago');
    $paymentTypesSheet->setCellValue("A1", "Descripción");
    $paymentTypesSheet->setCellValue("B1", "Código Interno");

    $paymentTypesSheet->setCellValue("A2", "Efectivo");
    $paymentTypesSheet->setCellValue("B2", Movement::PAYMENT_METHOD_CASH);

    $paymentTypesSheet->setCellValue("A3", "Transferencia Bancaria");
    $paymentTypesSheet->setCellValue("B3", Movement::PAYMENT_METHOD_TRANSFER);

    $paymentTypesSheet->setCellValue("A4", "Cheque");
    $paymentTypesSheet->setCellValue("B4", Movement::PAYMENT_METHOD_CHECK);

    $paymentTypesSheet->setCellValue("A5", "Tarjeta de Crédito");
    $paymentTypesSheet->setCellValue("B5", Movement::PAYMENT_METHOD_CREDIT_CARD);

    $paymentTypesSheet->setCellValue("A6", "Tarjeta de Débito");
    $paymentTypesSheet->setCellValue("B6", Movement::PAYMENT_METHOD_DEBIT_CARD);

    $paymentTypesSheet->setCellValue("A7", "Otro Método de Pago");
    $paymentTypesSheet->setCellValue("B7", Movement::PAYMENT_METHOD_OTHER);

    $paymentTypesSheet->getColumnDimension('A')->setWidth(25);
    $paymentTypesSheet->getColumnDimension('B')->setWidth(20);

    // Aplicar validación de datos a la columna de tipo de pago (ahora columna F) en la hoja principal
    // con validación dependiente del proveedor seleccionado
    $mainSheet = $spreadsheet->getSheet(0); // Obtener la hoja principal

    // Para cada fila, crear una validación dinámica que dependa del proveedor seleccionado
    for ($i = 2; $i <= 500; $i++) {
        $paymentValidation = $mainSheet->getCell("F$i")->getDataValidation();
        $paymentValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $paymentValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $paymentValidation->setAllowBlank(false);
        $paymentValidation->setShowInputMessage(true);
        $paymentValidation->setShowErrorMessage(true);
        $paymentValidation->setShowDropDown(true);
        $paymentValidation->setErrorTitle('Error de entrada');
        $paymentValidation->setError('Este valor no es admitido. Debe seleccionar un tipo de pago válido para este proveedor.');
        $paymentValidation->setPromptTitle('Selecciona un tipo de pago');
        $paymentValidation->setPrompt('Selecciona cómo se pagó este insumo: efectivo, transferencia, etc.');

        // Fórmula que obtiene la referencia a la hoja de métodos de pago del proveedor seleccionado
        $paymentValidation->setFormula1('INDIRECT(CONCATENATE(VLOOKUP(E' . $i . ',Proveedores!A:C,3,FALSE),"!$A$2:$A$20"))');

        $mainSheet->getCell("F$i")->setDataValidation($paymentValidation);
    }

    // Agregar formato condicional para ocultar/mostrar columnas según el tipo de movimiento
    $mainSheet = $spreadsheet->getSheet(0);

    // Columna E (Proveedor) - gris si es Salida
    $providerConditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
    $providerConditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
    $providerConditional->addCondition('$A2="Salida"');
    $providerConditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $providerConditional->getStyle()->getFill()->getStartColor()->setRGB('CCCCCC');
    $providerConditional->getStyle()->getFont()->getColor()->setRGB('CCCCCC');
    $mainSheet->getStyle('E2:E500')->setConditionalStyles([$providerConditional]);

    // Columna F (Tipo de Pago) - gris si es Salida
    $paymentConditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
    $paymentConditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
    $paymentConditional->addCondition('$A2="Salida"');
    $paymentConditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $paymentConditional->getStyle()->getFill()->getStartColor()->setRGB('CCCCCC');
    $paymentConditional->getStyle()->getFont()->getColor()->setRGB('CCCCCC');
    $mainSheet->getStyle('F2:F500')->setConditionalStyles([$paymentConditional]);

    // Columna G (Factura) - gris si es Salida
    $invoiceConditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
    $invoiceConditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
    $invoiceConditional->addCondition('$A2="Salida"');
    $invoiceConditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $invoiceConditional->getStyle()->getFill()->getStartColor()->setRGB('CCCCCC');
    $invoiceConditional->getStyle()->getFont()->getColor()->setRGB('CCCCCC');
    $mainSheet->getStyle('G2:G500')->setConditionalStyles([$invoiceConditional]);

    // Columna H (Centro de Consumo) - gris si es Entrada
    $consumptionCenterConditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
    $consumptionCenterConditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
    $consumptionCenterConditional->addCondition('$A2="Entrada"');
    $consumptionCenterConditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $consumptionCenterConditional->getStyle()->getFill()->getStartColor()->setRGB('CCCCCC');
    $consumptionCenterConditional->getStyle()->getFont()->getColor()->setRGB('CCCCCC');
    $mainSheet->getStyle('H2:H500')->setConditionalStyles([$consumptionCenterConditional]);

    // Columna J (Precio de Compra) - gris si es Salida
    $priceConditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
    $priceConditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
    $priceConditional->addCondition('$A2="Salida"');
    $priceConditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $priceConditional->getStyle()->getFill()->getStartColor()->setRGB('CCCCCC');
    $priceConditional->getStyle()->getFont()->getColor()->setRGB('CCCCCC');
    $mainSheet->getStyle('J2:J500')->setConditionalStyles([$priceConditional]);

    // Columna K (Impuesto) - gris si es Salida
    $taxConditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
    $taxConditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
    $taxConditional->addCondition('$A2="Salida"');
    $taxConditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $taxConditional->getStyle()->getFill()->getStartColor()->setRGB('CCCCCC');
    $taxConditional->getStyle()->getFont()->getColor()->setRGB('CCCCCC');
    $mainSheet->getStyle('K2:K500')->setConditionalStyles([$taxConditional]);

    // Columna L (Precio Unitario) - gris si es Salida
    $unitPriceConditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
    $unitPriceConditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
    $unitPriceConditional->addCondition('$A2="Salida"');
    $unitPriceConditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $unitPriceConditional->getStyle()->getFill()->getStartColor()->setRGB('CCCCCC');
    $unitPriceConditional->getStyle()->getFont()->getColor()->setRGB('CCCCCC');
    $mainSheet->getStyle('L2:L500')->setConditionalStyles([$unitPriceConditional]);

    // Columna M (Total) - gris si es Salida
    $totalConditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
    $totalConditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
    $totalConditional->addCondition('$A2="Salida"');
    $totalConditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $totalConditional->getStyle()->getFill()->getStartColor()->setRGB('CCCCCC');
    $totalConditional->getStyle()->getFont()->getColor()->setRGB('CCCCCC');
    $mainSheet->getStyle('M2:M500')->setConditionalStyles([$totalConditional]);

    // Agregar fórmulas automáticas para cálculos
    $mainSheet = $spreadsheet->getSheet(0);

    for ($i = 2; $i <= 500; $i++) {
        // Fórmula para Precio Unitario (Columna L): (Precio de compra + Impuesto) / Cantidad - solo si es Entrada
        $mainSheet->setCellValue("L$i", "=IF(AND(A$i=\"Entrada\",ISNUMBER(I$i),I$i>0,OR(ISNUMBER(J$i),ISNUMBER(K$i))),IFERROR((IFERROR(J$i,0)+IFERROR(K$i,0))/I$i,\"\"),\"\")");

        // Fórmula para Total (Columna M): Cantidad * Precio Unitario - solo si es Entrada
        $mainSheet->setCellValue("M$i", "=IF(AND(A$i=\"Entrada\",ISNUMBER(I$i),I$i>0,ISNUMBER(L$i),L$i>0),IFERROR(I$i*L$i,\"\"),\"\")");
    }

    // Proteger las celdas de fórmulas (L y M) para que no se puedan editar
    $mainSheet->getStyle('L2:L500')->getProtection()->setLocked(true);
    $mainSheet->getStyle('M2:M500')->getProtection()->setLocked(true);

    // Aplicar formato numérico a las columnas de cálculo
    $mainSheet->getStyle('I2:M500')->getNumberFormat()->setFormatCode('#,##0.00');
    $mainSheet->getStyle('L2:M500')->getNumberFormat()->setFormatCode('#,##0.00');

    // Crear hoja de leyenda
    $legendSheet = $spreadsheet->createSheet();
    $legendSheet->setTitle('Instrucciones de uso');
    $legendSheet->setCellValue('A1', 'Columna');
    $legendSheet->setCellValue('B1', 'Descripción');
    $legendSheet->setCellValue('A2', 'Movimiento');
    $legendSheet->setCellValue('B2', 'IMPORTANTE: Seleccione "Entrada" para ingresos de stock o "Salida" para egresos de stock. Esta selección determina qué columnas están disponibles.');
    $legendSheet->setCellValue('A3', 'Entrada vs Salida');
    $legendSheet->setCellValue('B3', 'ENTRADA: Usa Proveedor, Tipo de Pago, Factura, Precio de Compra, Impuesto, Precio Unitario y Total. La columna Centro de Consumo se vuelve gris. SALIDA: Usa Centro de Consumo. Las columnas de proveedor, precios e impuestos se vuelven grises.');
    $legendSheet->setCellValue('A4', 'Clave');
    $legendSheet->setCellValue('B4', 'Seleccione una clave del desplegable. El insumo aparecerá automáticamente en la columna C mediante fórmula BUSCARV.');
    $legendSheet->setCellValue('A5', 'Insumo');
    $legendSheet->setCellValue('B5', 'OPCIÓN 1: Se completa automáticamente cuando selecciona una clave en B mediante fórmula BUSCARV. OPCIÓN 2: También puede seleccionar directamente del desplegable si no recuerda la clave. Si esta columna tiene contenido pero la clave está vacía, la celda de clave se pondrá gris.');
    $legendSheet->setCellValue('A6', 'Fecha');
    $legendSheet->setCellValue('B6', 'Formato: año-mes-día (ej: 2025-08-07)');
    $legendSheet->setCellValue('A7', 'Proveedor');
    $legendSheet->setCellValue('B7', 'SOLO PARA ENTRADAS: Seleccione un proveedor del desplegable. Si no tiene proveedores cargados o no está definido, puede seleccionar "Por definir". Se vuelve gris automáticamente si selecciona "Salida".');
    $legendSheet->setCellValue('A8', 'Tipo de Pago');
    $legendSheet->setCellValue('B8', 'SOLO PARA ENTRADAS: Seleccione un tipo de pago del desplegable (en español). Los tipos disponibles dependen del proveedor seleccionado. Se vuelve gris automáticamente si selecciona "Salida".');
    $legendSheet->setCellValue('A9', 'Centro de Consumo');
    $legendSheet->setCellValue('B9', 'SOLO PARA SALIDAS: Seleccione el centro de consumo donde se utilizó el insumo. Se vuelve gris automáticamente si selecciona "Entrada".');
    $legendSheet->setCellValue('A10', 'Cantidad');
    $legendSheet->setCellValue('B10', 'Ingrese un valor numérico. Aplica tanto para entradas como para salidas.');
    $legendSheet->setCellValue('A11', 'Precio de Compra');
    $legendSheet->setCellValue('B11', 'SOLO PARA ENTRADAS: Ingrese un valor numérico. Se vuelve gris automáticamente si selecciona "Salida".');
    $legendSheet->setCellValue('A12', 'Impuesto');
    $legendSheet->setCellValue('B12', 'SOLO PARA ENTRADAS: Ingrese un valor numérico. Se vuelve gris automáticamente si selecciona "Salida".');
    $legendSheet->setCellValue('A13', 'Precio Unitario');
    $legendSheet->setCellValue('B13', 'SOLO PARA ENTRADAS: SE CALCULA AUTOMÁTICAMENTE: (Precio de compra + Impuesto) ÷ Cantidad. CELDA PROTEGIDA: No se puede editar manualmente. Se vuelve gris automáticamente si selecciona "Salida".');
    $legendSheet->setCellValue('A14', 'Total');
    $legendSheet->setCellValue('B14', 'SOLO PARA ENTRADAS: SE CALCULA AUTOMÁTICAMENTE: Cantidad × Precio Unitario. CELDA PROTEGIDA: No se puede editar manualmente. Se vuelve gris automáticamente si selecciona "Salida".');
    $legendSheet->setCellValue('A15', 'Observaciones');
    $legendSheet->setCellValue('B15', '(Opcional) Notas adicionales. Aplica tanto para entradas como para salidas.');
    $legendSheet->setCellValue('A16', 'Autocompletado con BUSCARV');
    $legendSheet->setCellValue('B16', 'FUNCIONAMIENTO: Cuando seleccione una clave en columna B, el insumo correspondiente aparece AUTOMÁTICAMENTE en columna C mediante fórmula BUSCARV. Alternativamente, puede seleccionar el insumo directamente del desplegable en columna C.');
    $legendSheet->setCellValue('A17', 'Formato Condicional');
    $legendSheet->setCellValue('B17', 'ALERTA VISUAL: Las columnas se vuelven grises automáticamente según el tipo de movimiento. Si selecciona "Entrada", solo las columnas relevantes para entrada permanecen activas. Si selecciona "Salida", solo las columnas relevantes para salida permanecen activas.');
    $legendSheet->setCellValue('A18', 'Instrucciones de Uso');
    $legendSheet->setCellValue('B18', 'PASOS: 1) Seleccione tipo de movimiento (Entrada/Salida) - esto determinará qué columnas usar, 2) Seleccione una clave O un insumo, 3) Complete solo las columnas que no estén en gris. FLEXIBILIDAD: El sistema se adapta automáticamente al tipo de movimiento seleccionado.');
    $legendSheet->setCellValue('A19', 'Protección de Celdas');
    $legendSheet->setCellValue('B19', 'CELDAS PROTEGIDAS: Las columnas Precio Unitario (L) y Total (M) están protegidas y no se pueden editar manualmente ya que se calculan automáticamente. El resto de celdas sí se pueden editar libremente.');

    // Configurar estilo de título para la leyenda
    $titleStyle = [
        'font' => ['bold' => true, 'size' => 14],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'DDDDDD']
        ]
    ];

    // Configurar estilo de encabezados
    $headerStyle = [
        'font' => ['bold' => true, 'size' => 12],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F0F0F0']
        ]
    ];

    // Aplicar estilos
    $legendSheet->getStyle('A1:B1')->applyFromArray($titleStyle);
    $legendSheet->getStyle('A2:A19')->applyFromArray($headerStyle);

    $legendSheet->getColumnDimension('A')->setWidth(20);
    $legendSheet->getColumnDimension('B')->setWidth(80);

    // Activar la primera hoja antes de guardar
    $spreadsheet->setActiveSheetIndex(0);

    // Configurar protección de celdas
    $mainSheet = $spreadsheet->getActiveSheet();

    // Desbloquear las celdas editables: fila de headers + datos (excepto L y M)
    $mainSheet->getStyle('A1:N1')->getProtection()->setLocked(false);   // fila headers (necesario para AutoFilter)
    $mainSheet->getStyle('A2:K500')->getProtection()->setLocked(false);
    $mainSheet->getStyle('N2:N500')->getProtection()->setLocked(false);

    // FIX: las columnas auxiliares ocultas P y Q deben quedar bloqueadas
    // para que el usuario no pueda editarlas manualmente y romper la validación.
    $mainSheet->getStyle('P2:Q500')->getProtection()->setLocked(true);

    // Habilitar protección de la hoja (las celdas L, M, P y Q ya están bloqueadas)
    $mainSheet->getProtection()->setSheet(true);
    $mainSheet->getProtection()->setPassword('asd');
    $mainSheet->getProtection()->setSort(false);           // false = permitido en OOXML
    $mainSheet->getProtection()->setAutoFilter(false);     // false = permitido en OOXML (true lo bloquearía)
    $mainSheet->getProtection()->setInsertRows(true);
    $mainSheet->getProtection()->setDeleteRows(true);
    $mainSheet->getProtection()->setInsertColumns(true);
    $mainSheet->getProtection()->setDeleteColumns(true);
    $mainSheet->getProtection()->setFormatCells(false);
    $mainSheet->getProtection()->setFormatColumns(false);
    $mainSheet->getProtection()->setFormatRows(false);

    // Establecer la celda activa en A2 para que el usuario pueda empezar a llenar datos inmediatamente
    $spreadsheet->getActiveSheet()->setSelectedCell('A2');

    // Activar filtros rápidos en los encabezados
    $spreadsheet->getActiveSheet()->setAutoFilter('A1:N1');

    $writer = new Xlsx($spreadsheet);
    $fileName = 'Plantilla_para_importar_movimientos.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
    $writer->save('php://output');
    exit(200);
}
    public static function importIngredientsAdmin($fileName)
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileName);

        $ingredientData = [];

        $rowIterator = $spreadsheet->getActiveSheet()->getRowIterator();

        while (true) {
            $cellIterator = $rowIterator->current()->getCellIterator('A', 'C');
            if($rowIterator->current()->getRowIndex() != 1) {
                if (empty($cellIterator->current()->getValue())) {
                    break;
                }
                $data = [];
                $data[] = $cellIterator->current()->getValue(); // A - Clave
                $cellIterator->next();
                $data[] = $cellIterator->current()->getValue(); // B - Unidad de Medida
                $cellIterator->next();
                $data[] = $cellIterator->current()->getValue(); // C - Categoría


                /// extract category id
                $data[2] = explode(' - ', $data[2])[0];
                $data[2] = trim($data[2]);

                /// check if category exists
                $category = Category::find()
                    ->where([
                        'key_prefix' => $data[2],
                        'business_id' => null
                    ])
                    ->one();

                if(empty($category)){
                    throw new HttpException(400, "No existe ninguna categoría con el identificador \"{$data[2]}\"");
                }

                $data[2] = $category->id;

                $ingredientData[] = $data;

            }

            $rowIterator->next();
        }

        \Yii::$app->db->createCommand()
            ->batchInsert(
                'ingredient',
                ['name', 'um', 'category_id'],
                $ingredientData
            )
            ->execute();

        return;
    }

    public static function importMovements(Business $business, $fileName)
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileName);

        $movementsData = [];
        $errors = [];

        $rowIterator = $spreadsheet->getActiveSheet()->getRowIterator(2); // Empezar desde la fila 2 (datos)
        
        foreach ($rowIterator as $row) {
            $cellIterator = $row->getCellIterator('A', 'N');
            $rowNumber = $row->getRowIndex();
            
            // Obtener valores de las columnas principales para verificar si la fila tiene datos
            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = trim($cell->getValue());
            }
            
            $tipoMovimiento = $cells[0]; // Columna A
            $fecha = $cells[1]; // Columna B  
            $insumo = $cells[2]; // Columna C
            $clave = $cells[3]; // Columna D
            $cantidad = $cells[8]; // Columna I
            
            // Verificar si la fila tiene datos mínimos necesarios
            $hasMinimumData = !empty($tipoMovimiento) && 
                             (!empty($insumo) || !empty($clave)) && 
                             !empty($cantidad);
            
            if (!$hasMinimumData) {
                // Si no tiene los datos mínimos, salir del bucle (fin de datos)
                break;
            }
            
            $data = [];
            
            // Resetear el iterador a la primera columna
            $cellIterator = $row->getCellIterator('A', 'N');
                
            // A - Movimiento (Entrada/Salida)
            $movementTypeValue = trim($cellIterator->current()->getValue());
            
            // Convertir descripciones en español a códigos internos
            $movementTypeMap = [
                'Entrada' => Movement::TYPE_INPUT,
                'Salida' => Movement::TYPE_OUTPUT
            ];
            
            $data['type'] = isset($movementTypeMap[$movementTypeValue]) ? 
                $movementTypeMap[$movementTypeValue] : Movement::TYPE_INPUT; // Por defecto entrada
                $cellIterator->next();
                
                // B - Fecha
                try {
                    $dateValue = $cellIterator->current()->getValue();
                    $currentTime = date('H:i:s'); // Hora actual del momento de importación
                    
                    if (is_numeric($dateValue)) {
                        $createdAt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue);
                        $data['created_at'] = $createdAt->format('Y-m-d') . ' ' . $currentTime;
                    } else if (!empty($dateValue)) {
                        // Si no es numérico, intentar parsear como string de fecha
                        $parsedDate = date('Y-m-d', strtotime($dateValue));
                        $data['created_at'] = $parsedDate . ' ' . $currentTime;
                    } else {
                        // Si está vacío, usar fecha y hora actuales
                        $data['created_at'] = date('Y-m-d H:i:s');
                    }
                } catch (\Exception $e) {
                    $errors[] = "Fila $rowNumber: Error en formato de fecha";
                    $data['created_at'] = date('Y-m-d H:i:s'); // Fecha y hora actuales por defecto
                }
                $cellIterator->next();
               
                // C - Insumo (nombre)
                $data['ingredient_name'] = trim($cellIterator->current()->getValue());
                $cellIterator->next();
                
                // D - Clave
                $data['key'] = trim($cellIterator->current()->getValue());
                $cellIterator->next();
                
                // E - Proveedor (solo para entradas)
                $providerValue = trim($cellIterator->current()->getValue());
                // Solo asignar proveedor si es entrada
                if ($data['type'] === Movement::TYPE_INPUT) {
                    // Para entradas, convertir "Por definir" y valores vacíos a null
                    if ($providerValue === 'Por definir' || empty($providerValue)) {
                        $data['provider'] = null;
                    } else {
                        $data['provider'] = $providerValue;
                    }
                } else {
                    // Para salidas, ignorar este campo (está en gris en la plantilla)
                    $data['provider'] = null;
                }
                $cellIterator->next();
                
                // F - Tipo de Pago (solo para entradas)
                $paymentTypeValue = trim($cellIterator->current()->getValue());
                
                if ($data['type'] === Movement::TYPE_INPUT) {
                    // Convertir descripciones en español a códigos internos solo para entradas
                    $paymentTypeMap = [
                        'Efectivo' => Movement::PAYMENT_METHOD_CASH,
                        'Transferencia Bancaria' => Movement::PAYMENT_METHOD_TRANSFER,
                        'Cheque' => Movement::PAYMENT_METHOD_CHECK,
                        'Tarjeta de Crédito' => Movement::PAYMENT_METHOD_CREDIT_CARD,
                        'Tarjeta de Débito' => Movement::PAYMENT_METHOD_DEBIT_CARD,
                        'Otro Método de Pago' => Movement::PAYMENT_METHOD_OTHER
                    ];
                    
                    if ($paymentTypeValue === 'Por definir') {
                        $data['payment_type'] = null;
                    } else {
                        $data['payment_type'] = isset($paymentTypeMap[$paymentTypeValue]) ? 
                            $paymentTypeMap[$paymentTypeValue] : $paymentTypeValue;
                    }
                } else {
                    // Para salidas, ignorar este campo (está en gris en la plantilla)
                    $data['payment_type'] = null;
                }
                $cellIterator->next();
                
                // G - Factura (solo para entradas)
                $invoiceValue = trim($cellIterator->current()->getValue());
                if ($data['type'] === Movement::TYPE_INPUT) {
                    $data['invoice'] = $invoiceValue;
                } else {
                    // Para salidas, ignorar este campo (está en gris en la plantilla)
                    $data['invoice'] = null;
                }
                $cellIterator->next();
                
                // H - Centro de Consumo (solo para salidas)
                $consumptionCenterValue = trim($cellIterator->current()->getValue());
                
                // Para movimientos de salida, buscar el centro de consumo por nombre
                if ($data['type'] === Movement::TYPE_OUTPUT && !empty($consumptionCenterValue)) {
                    // Buscar el centro de consumo por nombre
                    $consumptionCenter = ConsumptionCenter::find()
                        ->where(['business_id' => $business->id])
                        ->andWhere(['name' => $consumptionCenterValue])
                        ->one();
                    
                    if ($consumptionCenter) {
                        $data['consumption_center_id'] = $consumptionCenter->id;
                    } else {
                        // Si no existe, crear el centro de consumo automáticamente
                        $newConsumptionCenter = new ConsumptionCenter();
                        $newConsumptionCenter->name = $consumptionCenterValue;
                        $newConsumptionCenter->business_id = $business->id;
                        if ($newConsumptionCenter->save()) {
                            $data['consumption_center_id'] = $newConsumptionCenter->id;
                        } else {
                            $data['consumption_center_id'] = null;
                        }
                    }
                } else {
                    // Para entradas, no usar centro de consumo
                    $data['consumption_center_id'] = null;
                }
                $cellIterator->next();
                
                // I - Cantidad (para ambos tipos)
                $data['quantity'] = $cellIterator->current()->getValue();
                $cellIterator->next();
                
                // J - Precio de Compra (solo para entradas)
                $amountValue = $cellIterator->current()->getValue();
                if ($data['type'] === Movement::TYPE_INPUT) {
                    $data['amount'] = $amountValue;
                } else {
                    // Para salidas, ignorar este campo (está en gris en la plantilla)
                    $data['amount'] = 0;
                }
                $cellIterator->next();
                
                // K - Impuesto (solo para entradas)
                $taxValue = $cellIterator->current()->getValue();
                if ($data['type'] === Movement::TYPE_INPUT) {
                    $data['tax'] = $taxValue;
                } else {
                    // Para salidas, ignorar este campo (está en gris en la plantilla)
                    $data['tax'] = 0;
                }
                $cellIterator->next();
                
                // L - Precio Unitario (solo para entradas)
                $unitPriceValue = $cellIterator->current()->getValue();
                $cellIterator->next();
                
                // M - Total (solo para entradas)
                $totalValue = $cellIterator->current()->getValue();
                $cellIterator->next();
                
                // N - Observaciones (para ambos tipos)
                $data['observations'] = trim($cellIterator->current()->getValue());
                
                // Procesar precios solo para entradas
                if ($data['type'] === Movement::TYPE_INPUT) {
                    // Calcular unit_price y total si son fórmulas o están vacíos
                    $quantity = floatval($data['quantity']);
                    $amount = floatval($data['amount']); // Precio de compra
                    $tax = floatval($data['tax']); // Impuesto
                    
                    // Calcular unit_price: (Precio de compra + Impuesto) / Cantidad
                    if ($quantity > 0) {
                        $calculatedUnitPrice = ($amount + $tax) / $quantity;
                    } else {
                        $calculatedUnitPrice = 0;
                    }
                    
                    // Calcular total: Cantidad × Precio Unitario
                    $calculatedTotal = $quantity * $calculatedUnitPrice;
                    
                    // Usar valores calculados si el valor de Excel es una fórmula o está vacío
                    if (is_string($unitPriceValue) && (strpos($unitPriceValue, '=') === 0 || empty($unitPriceValue))) {
                        $data['unit_price'] = $calculatedUnitPrice;
                    } else {
                        $data['unit_price'] = floatval($unitPriceValue);
                    }
                    
                    if (is_string($totalValue) && (strpos($totalValue, '=') === 0 || empty($totalValue))) {
                        $data['total'] = $calculatedTotal;
                    } else {
                        $data['total'] = floatval($totalValue);
                    }
                } else {
                    // Para salidas, establecer valores por defecto para los campos de precio
                    $data['unit_price'] = 0;
                    $data['total'] = 0;
                }

                $data['business_id'] = $business->id;
                $data['row_number'] = $rowNumber; // Para tracking de errores

                $movementsData[] = $data;
        }

        // Procesar los datos y buscar ingredientes
        $processedMovements = [];
        foreach ($movementsData as $movement) {
            $ingredient = null;
            $rowNumber = $movement['row_number'];
            
            // Limpiar el nombre del ingrediente removiendo contenido entre paréntesis
            $originalIngredientName = $movement['ingredient_name'];
            $cleanIngredientName = '';
            if (!empty($originalIngredientName)) {
                // Remover contenido entre paréntesis y espacios extra
                $cleanIngredientName = trim(preg_replace('/\s*\([^)]*\)\s*/', '', $originalIngredientName));
            }
            
            // Estrategia de búsqueda mejorada:
            // 1. Primero por clave si está disponible
            // if (!empty($movement['key'])) {
            //     $ingredient = IngredientStock::find()
            //         ->where([
            //             'key' => $movement['key'],
            //             'business_id' => $movement['business_id']
            //         ])
            //         ->one();
            // }
                
            // 2. Si no se encuentra por clave, buscar por nombre limpio (sin paréntesis) exacto
            if (!$ingredient && !empty($cleanIngredientName)) {
                $ingredient = IngredientStock::find()
                    ->where([
                        'ingredient' => $cleanIngredientName,
                        'business_id' => $movement['business_id']
                    ])
                    ->one();
            }
            
            // 3. Si aún no se encuentra, buscar por nombre limpio con LIKE (búsqueda parcial)
            if (!$ingredient && !empty($cleanIngredientName)) {
                $ingredient = IngredientStock::find()
                    ->where(['like', 'ingredient', $cleanIngredientName, false])
                    ->andWhere(['business_id' => $movement['business_id']])
                    ->one();
            }
            
            // 4. Búsqueda por palabras clave del nombre limpio
            if (!$ingredient && !empty($cleanIngredientName)) {
                $words = explode(' ', $cleanIngredientName);
                if (count($words) > 1) {
                    // Buscar por la primera palabra significativa (más de 2 caracteres)
                    foreach ($words as $word) {
                        if (strlen(trim($word)) > 2) {
                            $ingredient = IngredientStock::find()
                                ->where(['like', 'ingredient', trim($word), false])
                                ->andWhere(['business_id' => $movement['business_id']])
                                ->one();
                            if ($ingredient) {
                                break;
                            }
                        }
                    }
                }
            }
            
            // 5. Como último recurso, buscar por el nombre original (con paréntesis)
            if (!$ingredient && !empty($originalIngredientName)) {
                $ingredient = IngredientStock::find()
                    ->where(['like', 'ingredient', $originalIngredientName, false])
                    ->andWhere(['business_id' => $movement['business_id']])
                    ->one();
            }
            
            // Limpiar datos innecesarios
            unset($movement['key']);
            unset($movement['ingredient_name']);
            // Guardar el número de fila como referencia (sin enviarlo al modelo Movement)
            $movement['row_number_ref'] = $movement['row_number'];
            unset($movement['row_number']);
            
            if ($ingredient) {
                $movement['ingredient_id'] = $ingredient->id;
                
                // Asegurarse de que um esté definido desde el ingrediente
                $movement['um'] = $ingredient->portion_um;
                
                // Validar que los movimientos de salida tengan centro de consumo
                if ($movement['type'] === Movement::TYPE_OUTPUT && empty($movement['consumption_center_id'])) {
                    $errors[] = "Fila $rowNumber: Los movimientos de salida deben tener un centro de consumo especificado en la columna H";
                    continue;
                }
                
                // Para movimientos de entrada, el proveedor puede ser null (cuando es "Por definir")
                // No agregamos validación adicional para entrada ya que provider puede ser null
                
                $processedMovements[] = $movement;
            } else {
                $errors[] = "Fila $rowNumber: No se encontró el insumo especificado";
            }
        }

        // Guardar movimientos válidos
        $savedCount = 0;
        $inputCount = 0;
        $outputCount = 0;
        
        // Contar tipos de movimientos para logging
        foreach ($processedMovements as $movData) {
            if ($movData['type'] === Movement::TYPE_INPUT) {
                $inputCount++;
            } elseif ($movData['type'] === Movement::TYPE_OUTPUT) {
                $outputCount++;
            }
        }

        // Caché de stock disponible por ingrediente (para validar múltiples salidas del mismo insumo)
        $stockCache = [];
        
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            foreach ($processedMovements as $movementData) {

                // Validar stock disponible para salidas antes de guardar
                if ($movementData['type'] === Movement::TYPE_OUTPUT) {
                    $ingredientId = $movementData['ingredient_id'];

                    // Cargar stock actual si no está en caché
                    if (!isset($stockCache[$ingredientId])) {
                        $ing = IngredientStock::findOne($ingredientId);
                        $stockCache[$ingredientId] = $ing ? (float)$ing->quantity : 0;
                    }

                    $stockDisponible = $stockCache[$ingredientId];
                    $cantidadSolicitada = (float)$movementData['quantity'];

                    if ($stockDisponible < $cantidadSolicitada) {
                        $ing = IngredientStock::findOne($ingredientId);
                        $nombreInsumo = $ing ? $ing->ingredient : "ID $ingredientId";
                        $errors[] = "Fila {$movementData['row_number_ref']}: Salida de \"$nombreInsumo\" no importada — stock insuficiente (disponible: $stockDisponible, solicitado: $cantidadSolicitada)";
                        continue;
                    }

                    // Descontar del caché para las siguientes filas del mismo insumo
                    $stockCache[$ingredientId] -= $cantidadSolicitada;
                }

                // Quitar campo interno antes de pasar al modelo
                unset($movementData['row_number_ref']);
                $movement = new Movement($movementData);
                // El tipo ya viene definido desde el Excel en $movementData['type']
                
                if ($movement->save()) {
                    $savedCount++;

                    // Actualizar caché de stock para entradas (suman stock)
                    // para que las salidas posteriores del mismo insumo vean el stock correcto
                    if ($movementData['type'] === Movement::TYPE_INPUT) {
                        $ingredientId = $movementData['ingredient_id'];
                        $ing = IngredientStock::findOne($ingredientId);
                        if (isset($stockCache[$ingredientId])) {
                            $stockCache[$ingredientId] = $ing ? (float)$ing->quantity : $stockCache[$ingredientId];
                        }
                    }
                } else {
                    $validationErrors = [];
                    foreach ($movement->errors as $field => $fieldErrors) {
                        $validationErrors[] = "$field: " . implode(', ', $fieldErrors);
                    }
                    
                    // Log más detallado para debugging
                    $movementDetails = json_encode([
                        'type' => $movementData['type'] ?? 'undefined',
                        'ingredient_id' => $movementData['ingredient_id'] ?? 'undefined',
                        'quantity' => $movementData['quantity'] ?? 'undefined',
                        'provider' => $movementData['provider'] ?? 'null',
                        'amount' => $movementData['amount'] ?? 'undefined',
                        'business_id' => $movementData['business_id'] ?? 'undefined'
                    ]);
                    
                    $errors[] = "Error al guardar movimiento (datos clave: $movementDetails): " . implode('; ', $validationErrors);
                    
                    // También log para Yii
                    \Yii::error("Error validación movimiento: " . implode('; ', $validationErrors) . " - Datos: " . $movementDetails);
                }
            }
            
            $transaction->commit();
            
            // Log de resultados
            \Yii::info("Importación de movimientos completada. Guardados: $savedCount");
            if (!empty($errors)) {
                \Yii::warning("Errores durante la importación: " . implode(', ', $errors));
            }
            
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw new HttpException(500, "Error durante la importación: " . $e->getMessage());
        }

        return [
            'success' => true,
            'saved_count' => $savedCount,
            'errors' => $errors
        ];
    }

    public static function exportIngredients(Business $business){
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();

        $activeWorksheet->setCellValue("A1", "Clave");
        $activeWorksheet->setCellValue("B1", "Insumo");
        $activeWorksheet->setCellValue("C1", "Marca");
        $activeWorksheet->setCellValue("D1", "Presentación");
        $activeWorksheet->setCellValue("E1", "Familia de Insumos");
        $activeWorksheet->setCellValue("F1", "Unidad de compra");
        $activeWorksheet->setCellValue("G1", "Unidad de uso");
        $activeWorksheet->setCellValue("H1", "Factor de Rendimiento");
        $activeWorksheet->setCellValue("I1", "Equivalencia");
        $activeWorksheet->setCellValue("J1", "Precio");
        $activeWorksheet->setCellValue("K1", "Observaciones");
        $activeWorksheet->freezePane("C2");

        $ingredients = $business->getIngredientStocks()->all();
        $currentRow = 2;
        foreach ($ingredients as $ingredient){
            /** @var $ingredient IngredientStock */
            $activeWorksheet->setCellValue("A$currentRow", $ingredient->key);
            $activeWorksheet->setCellValue("B$currentRow", $ingredient->ingredient);
            $activeWorksheet->setCellValue("C$currentRow", $ingredient->brand);
            $activeWorksheet->setCellValue("D$currentRow", $ingredient->presentation);
            $activeWorksheet->setCellValue("E$currentRow", $ingredient->category->name);
            $activeWorksheet->setCellValue("F$currentRow", $ingredient->um);
            $activeWorksheet->setCellValue("G$currentRow", $ingredient->portion_um);
            $activeWorksheet->setCellValue("H$currentRow", $ingredient->yield);
            $activeWorksheet->setCellValue("I$currentRow", $ingredient->portions_per_unit);
            $activeWorksheet->setCellValue("J$currentRow", $ingredient->lastPrice);
            $activeWorksheet->setCellValue("K$currentRow", $ingredient->observations);

            $currentRow++;
        }

        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('K')->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Catálogo_de_insumos.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        $writer->save('php://output');
        exit(200);
    }

    public static function exportMovements(Business $business, $ids = null)
    {
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();

        $activeWorksheet->setCellValue("A1", "Tipo de movimiento");
        $activeWorksheet->setCellValue("B1", "Clave");
        $activeWorksheet->setCellValue("C1", "Insumo");
        $activeWorksheet->setCellValue("D1", "Fecha");
        $activeWorksheet->setCellValue("E1", "Proveedor");
        $activeWorksheet->setCellValue("F1", "Tipo de Pago");
        $activeWorksheet->setCellValue("G1", "Factura");
        $activeWorksheet->setCellValue("H1", "Cantidad");
        $activeWorksheet->setCellValue("I1", "Precio de Compra");
        $activeWorksheet->setCellValue("J1", "Impuesto");
        $activeWorksheet->setCellValue("K1", "Precio Unitario");
        $activeWorksheet->setCellValue("L1", "Total");
        $activeWorksheet->setCellValue("M1", "Observaciones");
        $activeWorksheet->setCellValue("N1", "Centro de Consumo");
        $activeWorksheet->setCellValue("O1", "Número de Requisición");
        $activeWorksheet->setCellValue("P1", "Stock Disponible");

        // Si se proporcionan IDs, filtrar por ellos; de lo contrario, exportar todos
        $query = Movement::find()->where(['business_id' => $business->id]);
        if (!empty($ids) && $ids !== 'all') {
            // Asegurar que los IDs son integers
            $ids = array_map('intval', (array)$ids);
            $query->andWhere(['id' => $ids]);
        }
        $movements = $query->all();
        
        $currentRow = 2;
        foreach ($movements as $movement){
            // Si es una requisición, expandir mostrando cada item
            if ($movement->type === Movement::TYPE_REQUISITION) {
                $items = $movement->requisitionItems;
                
                if (count($items) > 0) {
                    // Crear una fila por cada item de la requisición
                    foreach ($items as $item) {
                        $ingredient = $item->ingredient;
                        
                        if ($ingredient) {
                            $activeWorksheet->setCellValue("A$currentRow", $movement->getFormattedType());
                            $activeWorksheet->setCellValue("B$currentRow", $ingredient->key ?? '');
                            $activeWorksheet->setCellValue("C$currentRow", $ingredient->ingredient ?? '');
                            $activeWorksheet->setCellValue("D$currentRow", \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel(\DateTime::createFromFormat('Y-m-d H:i:s', $movement->created_at)));
                            $activeWorksheet->setCellValue("E$currentRow", '');
                            $activeWorksheet->setCellValue("F$currentRow", '');
                            $activeWorksheet->setCellValue("G$currentRow", '');
                            $activeWorksheet->setCellValue("H$currentRow", $item->quantity_requested);
                            $activeWorksheet->setCellValue("I$currentRow", 0);
                            $activeWorksheet->setCellValue("J$currentRow", 0);
                            $activeWorksheet->setCellValue("K$currentRow", 0);
                            $activeWorksheet->setCellValue("L$currentRow", 0);
                            $activeWorksheet->setCellValue("M$currentRow", $movement->observations ?? '');
                            $activeWorksheet->setCellValue("N$currentRow", $movement->consumptionCenter ? $movement->consumptionCenter->name : '');
                            $activeWorksheet->setCellValue("O$currentRow", $movement->requisition_number ?? '');
                            $activeWorksheet->setCellValue("P$currentRow", $ingredient->quantity ?? 0);
                            
                            $currentRow++;
                        }
                    }
                } else {
                    // Requisición sin items
                    $activeWorksheet->setCellValue("A$currentRow", $movement->getFormattedType());
                    $activeWorksheet->setCellValue("B$currentRow", '');
                    $activeWorksheet->setCellValue("C$currentRow", 'Sin insumos');
                    $activeWorksheet->setCellValue("D$currentRow", \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel(\DateTime::createFromFormat('Y-m-d H:i:s', $movement->created_at)));
                    $activeWorksheet->setCellValue("E$currentRow", '');
                    $activeWorksheet->setCellValue("F$currentRow", '');
                    $activeWorksheet->setCellValue("G$currentRow", '');
                    $activeWorksheet->setCellValue("H$currentRow", '');
                    $activeWorksheet->setCellValue("I$currentRow", 0);
                    $activeWorksheet->setCellValue("J$currentRow", 0);
                    $activeWorksheet->setCellValue("K$currentRow", 0);
                    $activeWorksheet->setCellValue("L$currentRow", 0);
                    $activeWorksheet->setCellValue("M$currentRow", $movement->observations ?? '');
                    $activeWorksheet->setCellValue("N$currentRow", $movement->consumptionCenter ? $movement->consumptionCenter->name : '');
                    $activeWorksheet->setCellValue("O$currentRow", $movement->requisition_number ?? '');
                    $activeWorksheet->setCellValue("P$currentRow", '');
                    
                    $currentRow++;
                }
            } else {
                // Otros tipos de movimientos (entrada, salida, orden)
                $activeWorksheet->setCellValue("A$currentRow", $movement->getFormattedType());
                
                if ($movement->ingredient) {
                    $activeWorksheet->setCellValue("B$currentRow", $movement->ingredient->key ?? '');
                    $activeWorksheet->setCellValue("C$currentRow", $movement->ingredient->ingredient ?? '');
                } else {
                    $activeWorksheet->setCellValue("B$currentRow", '');
                    $activeWorksheet->setCellValue("C$currentRow", 'N/A');
                }
                
                $activeWorksheet->setCellValue("D$currentRow", \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel(\DateTime::createFromFormat('Y-m-d H:i:s', $movement->created_at)));
                $activeWorksheet->setCellValue("E$currentRow", $movement->provider ?? '');
                $activeWorksheet->setCellValue("F$currentRow", $movement->getFormattedPaymentType());
                $activeWorksheet->setCellValue("G$currentRow", $movement->invoice ?? '');
                $activeWorksheet->setCellValue("H$currentRow", $movement->quantity ?? '');
                $activeWorksheet->setCellValue("I$currentRow", $movement->amount ?? 0);
                $activeWorksheet->setCellValue("J$currentRow", $movement->tax ?? 0);
                $activeWorksheet->setCellValue("K$currentRow", $movement->unit_price ?? 0);
                $activeWorksheet->setCellValue("L$currentRow", $movement->total ?? 0);
                $activeWorksheet->setCellValue("M$currentRow", $movement->observations ?? '');
                $activeWorksheet->setCellValue("N$currentRow", $movement->consumptionCenter ? $movement->consumptionCenter->name : '');
                $activeWorksheet->setCellValue("O$currentRow", '');
                $activeWorksheet->setCellValue("P$currentRow", $movement->ingredient ? ($movement->ingredient->quantity ?? 0) : '');
                
                $currentRow++;
            }
        }

        $spreadsheet->getActiveSheet()->getStyle("I2:L$currentRow")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD);
        $spreadsheet->getActiveSheet()->getStyle("D2:D$currentRow")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD);

        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('K')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('L')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('M')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('N')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('O')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('P')->setAutoSize(true);


        $writer = new Xlsx($spreadsheet);
        $fileName = 'Movimientos.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        $writer->save('php://output');
        exit(200);
    }

    public static function generateAdminIngredientsTemplate()
    {
        /** @var Category[] $categories */
        $categories = Category::find()->where([
            'or',
            ['business_id' => null],
        ])->all();

        /** @var UnitOfMeasurement[] $unitOfMeasurements */
        $unitOfMeasurements = UnitOfMeasurement::find()
            ->select('name')
            ->groupBy('name')
            ->all();

        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();

        $activeWorksheet->setCellValue("A1", "Nombre");
        $activeWorksheet->setCellValue("B1", "Unidad de Medida");
        $activeWorksheet->setCellValue("C1", "Categoría");


        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);

        // Create a named range for categories
        $categorySheet = $spreadsheet->createSheet();
        $categorySheet->setTitle('Categorias');
        $categorySheet->setCellValue('A1', 'Categoría');

        $row = 2;
        foreach ($categories as $category) {
            $categorySheet->setCellValue("A$row", sprintf("%s - %s", $category->key_prefix, $category->name));
            $row++;
        }

        $spreadsheet->addNamedRange(
            new \PhpOffice\PhpSpreadsheet\NamedRange('Categorias', $categorySheet, 'A2:A' . ($row - 1))
        );



        // Apply data validation to the category column
        $dataValidation = $spreadsheet->getActiveSheet()->getCell('C2')->getDataValidation();
        $dataValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $dataValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $dataValidation->setAllowBlank(false);
        $dataValidation->setShowInputMessage(true);
        $dataValidation->setShowErrorMessage(true);
        $dataValidation->setShowDropDown(true);
        $dataValidation->setErrorTitle('Error de entrada');
        $dataValidation->setError('Este valor no es admitido');
        $dataValidation->setPromptTitle('Selecciona una categoría');
        $dataValidation->setPrompt('Por favor, selecciona un valor del desplegable.');
        $dataValidation->setFormula1('=Categorias!$A$2:$A$' . ($row - 1));

        for ($i = 2; $i <= 5000; $i++) {
            $spreadsheet->getActiveSheet()->getCell("C$i")->setDataValidation(clone $dataValidation);
        }

        // Create a named range for unit of measuerements
        $umSheet = $spreadsheet->createSheet();
        $umSheet->setTitle('UMs');
        $umSheet->setCellValue('A1', 'Unidad de medida');

        $row = 2;
        foreach ($unitOfMeasurements as $um) {
            $umSheet->setCellValue("A$row", $um->name);
            $row++;
        }

        $spreadsheet->addNamedRange(
            new \PhpOffice\PhpSpreadsheet\NamedRange('UMs', $categorySheet, 'A2:A' . ($row - 1))
        );

        // Apply data validation to the um column
        $dataValidation = $spreadsheet->getActiveSheet()->getCell('B1')->getDataValidation();
        $dataValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $dataValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $dataValidation->setAllowBlank(false);
        $dataValidation->setShowInputMessage(true);
        $dataValidation->setShowErrorMessage(true);
        $dataValidation->setShowDropDown(true);
        $dataValidation->setErrorTitle('Error de entrada');
        $dataValidation->setError('Este valor no es admitido');
        $dataValidation->setPromptTitle('Selecciona una unidad de medida');
        $dataValidation->setPrompt('Por favor, selecciona un valor del desplegable.');
        $dataValidation->setFormula1('=UMs!$A$2:$A$' . ($row - 1));

        for ($i = 2; $i <= 5000; $i++) {
            $spreadsheet->getActiveSheet()->getCell("B$i")->setDataValidation(clone $dataValidation);
        }

        // Establecer la celda activa en A2 para que el usuario pueda empezar a llenar datos inmediatamente
        $spreadsheet->getActiveSheet()->setSelectedCell('A2');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Plantilla_para_importar_ingredientes.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        $writer->save('php://output');
        exit(200);
    }
    
    public static function importRecipe(Business $business, $fileName)
    {
        $errors = [];
        $savedCount = 0;

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileName);
            $recipeData = [];
            $ingredientData = [];

            $recipesSheet = $spreadsheet->getSheetByName('FICHA GENERAL DE LA RECETA');
            $ingredientsSheet = $spreadsheet->getSheetByName('INGREDIENTES');

            if ($recipesSheet === null) {
                $errors[] = 'Estás intentando importar una plantilla de subrecetas en la sección de recetas. Por favor, selecciona la plantilla correcta.';
                return ['success' => false, 'saved_count' => 0, 'errors' => $errors];
            }

            if ($ingredientsSheet === null) {
                $errors[] = 'La hoja "INGREDIENTES" no se encontró en el archivo Excel.';
                return ['success' => false, 'saved_count' => 0, 'errors' => $errors];
            }
            // Importar recetas
            $rowIterator = $recipesSheet->getRowIterator();
            $rowNumber = 1;
            while ($rowIterator->valid()) {
                $rowNumber = $rowIterator->current()->getRowIndex();
                if ($rowNumber == 1) {
                    $rowIterator->next();
                    continue;
                }
                $cellIterator = $rowIterator->current()->getCellIterator('A', 'M');
                $cellIterator->rewind();
                if (empty(trim($cellIterator->current()->getValue()))) {
                    $rowIterator->next();
                    continue;
                }
                $data = [];
                $data['title'] = trim($cellIterator->current()->getValue()); // A - Nombre
                if (empty($data['title'])) {
                    $errors[] = "Fila $rowNumber: El nombre de la receta no puede estar vacío.";
                }
                $cellIterator->next();
                $data['type_of_recipe'] = trim($cellIterator->current()->getValue()); // B - Tipo de Receta
                $cellIterator->next();
                $timeValue = trim($cellIterator->current()->getValue());
                $cellIterator->next();
                $timeUnit = trim($cellIterator->current()->getValue()); // Time unit (minutes, hours, days)
                $data['time_of_preparation'] = $timeValue . ' ' . $timeUnit;
                $cellIterator->next();
                $data['yield'] = trim($cellIterator->current()->getValue()); // E - Rendimiento
                $cellIterator->next();
                $data['yield_um'] = trim($cellIterator->current()->getValue()); // F - Unidad de medida final
                $cellIterator->next();
                $data['um'] = trim($cellIterator->current()->getValue()); // G - Unidad de medida final
                $cellIterator->next();
                $portionsValue = $cellIterator->current();
                $data['portions'] = $portionsValue->getCalculatedValue(); // H - Porciones
                if (!is_numeric($data['portions'])) {
                    // Try to clean/extract numeric value if it's not already numeric
                    $data['portions'] = preg_replace('/[^\d.]/', '', $data['portions']);
                    // If still empty or not numeric, default to 1
                    if (empty($data['portions']) || !is_numeric($data['portions'])) {
                        $data['portions'] = 1;
                    }
                }
                $cellIterator->next();
                $timeValue = trim($cellIterator->current()->getValue());
                $cellIterator->next();
                $timeUnit = trim($cellIterator->current()->getValue());
                $data['lifetime'] = $timeValue . ' ' . $timeUnit; // I-J - Duración
                $cellIterator->next();
                $priceRaw = trim($cellIterator->current()->getValue());
                if (is_numeric($priceRaw)) {
                    $data['price'] = floatval($priceRaw);
                } else {
                    $cleanPrice = preg_replace('/[^\d.]/', '', strval($priceRaw));
                    if (is_numeric($cleanPrice)) {
                        $data['price'] = floatval($cleanPrice);
                    } else {
                        $errors[] = "Fila $rowNumber: El precio debe ser un valor numérico.";
                        $data['price'] = 0;
                    }
                }
                $cellIterator->next();
                $convoyName = trim($cellIterator->current()->getValue()); // L - Convoy
                if (!empty($convoyName)) {
                    $convoy = Convoy::find()->where(['name' => $convoyName, 'business_id' => $business->id])->one();
                    if ($convoy) {
                        $data['convoy_id'] = $convoy->id;
                    } else {
                        $errors[] = "Fila $rowNumber: No se encontró el convoy '$convoyName'.";
                    }
                }
                $cellIterator->next();

                $data['business_id'] = $business->id;
                $data['row_number'] = $rowNumber;

                // Derive is_food from the recipe category (TYPE_MAIN only)
                $recipeCategory = RecipeCategory::find()
                    ->where([
                        'name' => $data['type_of_recipe'],
                        'business_id' => $business->id,
                        'type' => RecipeCategory::TYPE_MAIN,
                    ])
                    ->one();
                $data['is_food'] = $recipeCategory ? (bool)$recipeCategory->is_food : true;

                $recipeData[] = $data;
                $rowIterator->next();
            }
            // Importar ingredientes agrupados por receta
            $rowIterator = $ingredientsSheet->getRowIterator();
            $rowNumber = 1;
            while ($rowIterator->valid()) {
                $rowNumber = $rowIterator->current()->getRowIndex();
                if ($rowNumber == 1) {
                    $rowIterator->next();
                    continue;
                }
                $cellIterator = $rowIterator->current()->getCellIterator('A', 'F');
                $cellIterator->rewind();
                if (empty(trim($cellIterator->current()->getValue()))) {
                    $rowIterator->next();
                    continue;
                }
                $data = [];
                $data['recipe'] = trim($cellIterator->current()->getValue()); // A - Receta
                $cellIterator->next();
                $data['type'] = trim($cellIterator->current()->getValue()); // B - Tipo (INSUMO/SUBRECETA)
                $cellIterator->next();
                $cell = $cellIterator->current();
                $data['item'] = trim(self::resolveCellValue($cell)); // C - Item (Insumo o Subreceta)
                $cellIterator->next();
                $cell = $cellIterator->current();
                $data['quantity'] = self::resolveCellValue($cell); // D - Cantidad
                $cellIterator->next();
                $cell = $cellIterator->current();
                $data['portion_um'] = trim(self::resolveCellValue($cell)); // E - UM
                $cellIterator->next();
                $cell = $cellIterator->current();
                $data['lastPrice'] = self::resolveCellValue($cell); // F - Costo

                $data['business_id'] = $business->id;
                $data['row_number'] = $rowNumber;
                if (!isset($ingredientData[$data['recipe']])) {
                    $ingredientData[$data['recipe']] = [];
                }
                $ingredientData[$data['recipe']][] = $data;
                $rowIterator->next();
            }

            // VALIDACIÓN PREVIA: Verificar todas las recetas e ingredientes antes de guardar
            $validationErrors = [];
            $recipeObjects = []; // Para almacenar las instancias de receta validadas

            foreach ($recipeData as $data) {
                $rowNumber = $data['row_number'];
                unset($data['row_number']);

                $recipe = new StandardRecipe();
                $recipe['type'] = \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_MAIN;
                $recipe->load($data, '');

                if (!$recipe->validate()) {
                    foreach ($recipe->errors as $attribute => $errorMessages) {
                        foreach ($errorMessages as $error) {
                            if (strpos($error, 'ya está en uso') !== false || strpos($error, 'already taken') !== false) {
                                $validationErrors[] = "Fila $rowNumber: El nombre de la receta '{$data['title']}' ya existe.";
                            } else {
                                $validationErrors[] = "Fila $rowNumber ($attribute): $error";
                            }
                        }
                    }
                    continue;
                }

                $recipeObjects[$data['title']] = $recipe;

                // Validar ingredientes de esta receta
                if (isset($ingredientData[$data['title']])) {
                    foreach ($ingredientData[$data['title']] as $ingredient) {
                        $ingRow = $ingredient['row_number'];

                        // Validar tipo
                        if (empty($ingredient['type']) || !in_array($ingredient['type'], ['INSUMO', 'SUBRECETA'])) {
                            $validationErrors[] = "Fila $ingRow: El tipo debe ser 'INSUMO' o 'SUBRECETA'.";
                            continue;
                        }

                        // Validar que el item no esté vacío
                        if (empty($ingredient['item'])) {
                            $validationErrors[] = "Fila $ingRow: El nombre del insumo/subreceta no puede estar vacío.";
                            continue;
                        }

                        // Validar cantidad
                        if (!is_numeric($ingredient['quantity']) || $ingredient['quantity'] <= 0) {
                            $validationErrors[] = "Fila $ingRow: La cantidad debe ser un número positivo.";
                            continue;
                        }

                        // Validar UM
                        if (empty($ingredient['portion_um'])) {
                            $validationErrors[] = "Fila $ingRow: La unidad de medida no puede estar vacía.";
                            continue;
                        }

                        // Validar precio (opcional, pero si está presente debe ser numérico)
                        if (isset($ingredient['lastPrice']) && $ingredient['lastPrice'] !== '' && !is_numeric($ingredient['lastPrice'])) {
                            $validationErrors[] = "Fila $ingRow: El precio debe ser un número válido.";
                            continue;
                        }

                        if ($ingredient['type'] === 'INSUMO') {
                            $ingredientStock = IngredientStock::find()
                                ->where(['ingredient' => $ingredient['item'], 'business_id' => $business->id])
                                ->one();

                            if (!$ingredientStock) {
                                $validationErrors[] = "Fila $ingRow: No se encontró el insumo \"{$ingredient['item']}\" en el catálogo de insumos.";
                            }
                        } else if ($ingredient['type'] === 'SUBRECETA') {
                            $subrecipe = StandardRecipe::find()
                                ->where([
                                    'title' => $ingredient['item'],
                                    'business_id' => $business->id,
                                    'type' => \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_SUB
                                ])
                                ->one();

                            if (!$subrecipe) {
                                $validationErrors[] = "Fila $ingRow: No se encontró la subreceta \"{$ingredient['item']}\" en las subrecetas.";
                            }
                        }
                    }
                }
            }

            // Si hay errores de validación, no guardar nada
            if (!empty($validationErrors)) {
                return ['success' => false, 'saved_count' => 0, 'errors' => $validationErrors];
            }

            // GUARDADO: Solo proceder si toda la validación pasó
            $transaction = \Yii::$app->db->beginTransaction();
            try {
                foreach ($recipeObjects as $recipeName => $recipe) {
                    if ($recipe->save()) {
                        $savedCount++;

                        // Procesar ingredientes (ya validados)
                        if (isset($ingredientData[$recipeName])) {
                            foreach ($ingredientData[$recipeName] as $ingredient) {
                                if ($ingredient['type'] === 'INSUMO') {
                                    $ingredientStock = IngredientStock::find()
                                        ->where(['ingredient' => $ingredient['item'], 'business_id' => $business->id])
                                        ->one();

                                    $ingredientRelation = new IngredientStandardRecipe();
                                    $ingredientRelation->ingredient_id = $ingredientStock->id;
                                    $ingredientRelation->standard_recipe_id = $recipe->id;
                                    $ingredientRelation->quantity = $ingredient['quantity'];
                                    $ingredientRelation->save();
                                } else if ($ingredient['type'] === 'SUBRECETA') {
                                    $subrecipe = StandardRecipe::find()
                                        ->where([
                                            'title' => $ingredient['item'],
                                            'business_id' => $business->id,
                                            'type' => \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_SUB
                                        ])
                                        ->one();

                                    Yii::$app->db->createCommand()
                                        ->insert(
                                            'standard_recipe_sub_standard_recipe',
                                            [
                                                'sub_standard_recipe_id' => $subrecipe->id,
                                                'quantity' => $ingredient['quantity'],
                                                'standard_recipe_id' => $recipe->id
                                            ]
                                        )
                                        ->execute();
                                }
                            }
                        }
                    }
                }
                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                $errors[] = "Error en la transacción: " . $e->getMessage();
                return ['success' => false, 'saved_count' => 0, 'errors' => $errors];
            }

            return ['success' => empty($errors), 'saved_count' => $savedCount, 'errors' => $errors];
        } catch (\Exception $e) {
            $errors[] = "Error general: " . $e->getMessage();
            return ['success' => false, 'saved_count' => 0, 'errors' => $errors];
        }
    }
    /**
     * Resolve a cell value preferring calculated value when the cell contains a formula.
     * Returns scalar or string. Arrays are imploded with comma.
     */
    private static function resolveCellValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell)
    {
        $val = null;
        try {
            if (method_exists($cell, 'isFormula') && $cell->isFormula()) {
                // Try calculated value first
                $val = $cell->getCalculatedValue();
                // Fallback to cached/old value when available
                if (($val === null || $val === '') && method_exists($cell, 'getOldCalculatedValue')) {
                    $val = $cell->getOldCalculatedValue();
                }
            } else {
                $val = $cell->getValue();
            }
        } catch (\Exception $e) {
            // In case calculation fails, fallback to raw value
            $val = $cell->getValue();
        }

        if (is_array($val)) {
            return implode(',', $val);
        }
        if (is_object($val) && method_exists($val, '__toString')) {
            return (string)$val;
        }

        return $val;
    }
    public static function importSubRecipe(Business $business, $fileName)
    {
        $errors = [];
        $savedCount = 0;

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileName);
            $recipeData = [];
            $ingredientData = [];

            $recipesSheet = $spreadsheet->getSheetByName('FICHA GENERAL DE LA SUBRECETA');
            $ingredientsSheet = $spreadsheet->getSheetByName('INGREDIENTES');

            if ($recipesSheet === null) {
                $errors[] = 'Estás intentando importar una plantilla de recetas en la sección de subrecetas. Por favor, selecciona la plantilla correcta.';
                return ['success' => false, 'saved_count' => 0, 'errors' => $errors];
            }

            if ($ingredientsSheet === null) {
                $errors[] = 'La hoja "INGREDIENTES" no se encontró en el archivo Excel.';
                return ['success' => false, 'saved_count' => 0, 'errors' => $errors];
            }

            // Importar subrecetas
            $rowIterator = $recipesSheet->getRowIterator();
            $rowNumber = 1;
            while ($rowIterator->valid()) {
                $rowNumber = $rowIterator->current()->getRowIndex();
                if ($rowNumber == 1) {
                    $rowIterator->next();
                    continue;
                }
                $cellIterator = $rowIterator->current()->getCellIterator('A', 'M');
                $cellIterator->rewind();
                if (empty(trim($cellIterator->current()->getValue()))) {
                    $rowIterator->next();
                    continue;
                }
                $data = [];
                $data['title'] = trim($cellIterator->current()->getValue()); // A - Nombre
                if (empty($data['title'])) {
                    $errors[] = "Fila $rowNumber: El nombre de la subreceta no puede estar vacío.";
                }
                $cellIterator->next();
                $data['type_of_recipe'] = trim($cellIterator->current()->getValue()); // B - Tipo de Receta
                $cellIterator->next();
                $timeValue = trim($cellIterator->current()->getValue());
                $cellIterator->next();
                $timeUnit = trim($cellIterator->current()->getValue()); // Time unit (minutes, hours, days)
                $data['time_of_preparation'] = $timeValue . ' ' . $timeUnit;
                $cellIterator->next();
                $data['yield'] = trim($cellIterator->current()->getValue()); // E - Rendimiento
                $cellIterator->next();
                $data['yield_um'] = trim($cellIterator->current()->getValue()); // F - Unidad de medida final
                $cellIterator->next();
                $data['um'] = trim($cellIterator->current()->getValue()); // G - Unidad de medida final
                $cellIterator->next();
                $portionsValue = $cellIterator->current();
                $data['portions'] = $portionsValue->getCalculatedValue(); // H - Porciones
                if (!is_numeric($data['portions'])) {
                    // Try to clean/extract numeric value if it's not already numeric
                    $data['portions'] = preg_replace('/[^\d.]/', '', $data['portions']);
                    // If still empty or not numeric, default to 1
                    if (empty($data['portions']) || !is_numeric($data['portions'])) {
                        $data['portions'] = 1;
                    }
                }
                $cellIterator->next();
                $timeValue = trim($cellIterator->current()->getValue());
                $cellIterator->next();
                $timeUnit = trim($cellIterator->current()->getValue());
                $data['lifetime'] = $timeValue . ' ' . $timeUnit; // I-J - Duración

                $data['business_id'] = $business->id;
                $data['row_number'] = $rowNumber;

                $recipeData[] = $data;
                $rowIterator->next();
            }

            // Importar ingredientes agrupados por subreceta
            $rowIterator = $ingredientsSheet->getRowIterator();
            $rowNumber = 1;
            while ($rowIterator->valid()) {
                $rowNumber = $rowIterator->current()->getRowIndex();
                if ($rowNumber == 1) {
                    $rowIterator->next();
                    continue;
                }
                $cellIterator = $rowIterator->current()->getCellIterator('A', 'F');
                $cellIterator->rewind();
                if (empty(trim($cellIterator->current()->getValue()))) {
                    $rowIterator->next();
                    continue;
                }
                $data = [];
                $data['recipe'] = trim($cellIterator->current()->getValue()); // A - Subreceta
                $cellIterator->next();
                $data['type'] = trim($cellIterator->current()->getValue()); // B - Tipo (INSUMO/SUBRECETA)
                $cellIterator->next();
                $cell = $cellIterator->current();
                $data['item'] = trim(self::resolveCellValue($cell)); // C - Item (Insumo o Subreceta)
                $cellIterator->next();
                $cell = $cellIterator->current();
                $data['quantity'] = self::resolveCellValue($cell); // D - Cantidad
                $cellIterator->next();
                $cell = $cellIterator->current();
                $data['portion_um'] = trim(self::resolveCellValue($cell)); // E - UM
                $cellIterator->next();
                $cell = $cellIterator->current();
                $data['lastPrice'] = self::resolveCellValue($cell); // F - Costo

                $data['business_id'] = $business->id;
                $data['row_number'] = $rowNumber;
                if (!isset($ingredientData[$data['recipe']])) {
                    $ingredientData[$data['recipe']] = [];
                }
                $ingredientData[$data['recipe']][] = $data;
                $rowIterator->next();
            }

            // VALIDACIÓN PREVIA: Verificar todas las subrecetas e ingredientes antes de guardar
            $validationErrors = [];
            $recipeObjects = []; // Para almacenar las instancias de subreceta validadas

            foreach ($recipeData as $data) {
                $rowNumber = $data['row_number'];
                unset($data['row_number']);

                $recipe = new StandardRecipe();
                $recipe['type'] = \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_SUB;
                $recipe->load($data, '');

                if (!$recipe->validate()) {
                    foreach ($recipe->errors as $attribute => $errorMessages) {
                        foreach ($errorMessages as $error) {
                            if (strpos($error, 'ya está en uso') !== false || strpos($error, 'already taken') !== false) {
                                $validationErrors[] = "La subreceta '{$data['title']}' ya existe en el sistema (fila $rowNumber). Por favor, use un nombre diferente o actualice la subreceta existente.";
                            } else {
                                $validationErrors[] = "Fila $rowNumber ($attribute): $error";
                            }
                        }
                    }
                    continue;
                }

                $recipeObjects[$data['title']] = $recipe;

                // Validar ingredientes de esta subreceta
                if (isset($ingredientData[$data['title']])) {
                    foreach ($ingredientData[$data['title']] as $ingredient) {
                        $ingRow = $ingredient['row_number'];

                        // Validar tipo
                        if (empty($ingredient['type']) || !in_array($ingredient['type'], ['INSUMO', 'SUBRECETA'])) {
                            $validationErrors[] = "Fila $ingRow: El tipo debe ser 'INSUMO' o 'SUBRECETA'.";
                            continue;
                        }

                        // Validar que el item no esté vacío
                        if (empty($ingredient['item'])) {
                            $validationErrors[] = "Fila $ingRow: El nombre del insumo/subreceta no puede estar vacío.";
                            continue;
                        }

                        // Validar cantidad
                        if (!is_numeric($ingredient['quantity']) || $ingredient['quantity'] <= 0) {
                            $validationErrors[] = "Fila $ingRow: La cantidad debe ser un número positivo.";
                            continue;
                        }

                        // Validar UM
                        if (empty($ingredient['portion_um'])) {
                            $validationErrors[] = "Fila $ingRow: La unidad de medida no puede estar vacía.";
                            continue;
                        }

                        // Validar precio (opcional, pero si está presente debe ser numérico)
                        if (isset($ingredient['lastPrice']) && $ingredient['lastPrice'] !== '' && !is_numeric($ingredient['lastPrice'])) {
                            $validationErrors[] = "Fila $ingRow: El precio debe ser un número válido.";
                            continue;
                        }

                        if ($ingredient['type'] === 'INSUMO') {
                            $ingredientStock = IngredientStock::find()
                                ->where(['ingredient' => $ingredient['item'], 'business_id' => $business->id])
                                ->one();

                            if (!$ingredientStock) {
                                $validationErrors[] = "Fila $ingRow: No se encontró el insumo \"{$ingredient['item']}\" en el catálogo.";
                            }
                        } else if ($ingredient['type'] === 'SUBRECETA') {
                            $subrecipe = StandardRecipe::find()
                                ->where([
                                    'title' => $ingredient['item'],
                                    'business_id' => $business->id,
                                    'type' => \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_SUB
                                ])
                                ->one();

                            if (!$subrecipe) {
                                $validationErrors[] = "Fila $ingRow: No se encontró la subreceta \"{$ingredient['item']}\" en las subrecetas.";
                            }
                        }
                    }
                }
            }

            // Si hay errores de validación, no guardar nada
            if (!empty($validationErrors)) {
                return ['success' => false, 'saved_count' => 0, 'errors' => $validationErrors];
            }

            // GUARDADO: Solo proceder si toda la validación pasó
            $transaction = \Yii::$app->db->beginTransaction();
            try {
                foreach ($recipeObjects as $recipeName => $recipe) {
                    if ($recipe->save()) {
                        $savedCount++;

                        // Procesar ingredientes (ya validados)
                        if (isset($ingredientData[$recipeName])) {
                            foreach ($ingredientData[$recipeName] as $ingredient) {
                                if ($ingredient['type'] === 'INSUMO') {
                                    $ingredientStock = IngredientStock::find()
                                        ->where(['ingredient' => $ingredient['item'], 'business_id' => $business->id])
                                        ->one();

                                    $ingredientRelation = new IngredientStandardRecipe();
                                    $ingredientRelation->ingredient_id = $ingredientStock->id;
                                    $ingredientRelation->standard_recipe_id = $recipe->id;
                                    $ingredientRelation->quantity = $ingredient['quantity'];
                                    $ingredientRelation->save();
                                } else if ($ingredient['type'] === 'SUBRECETA') {
                                    $subrecipe = StandardRecipe::find()
                                        ->where([
                                            'title' => $ingredient['item'],
                                            'business_id' => $business->id,
                                            'type' => \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_SUB
                                        ])
                                        ->one();

                                    Yii::$app->db->createCommand()
                                        ->insert(
                                            'standard_recipe_sub_standard_recipe',
                                            [
                                                'sub_standard_recipe_id' => $subrecipe->id,
                                                'quantity' => $ingredient['quantity'],
                                                'standard_recipe_id' => $recipe->id
                                            ]
                                        )
                                        ->execute();
                                }
                            }
                        }
                    }
                }
                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                $errors[] = "Error en la transacción: " . $e->getMessage();
                return ['success' => false, 'saved_count' => 0, 'errors' => $errors];
            }

            return ['success' => empty($errors), 'saved_count' => $savedCount, 'errors' => $errors];
        } catch (\Exception $e) {
            $errors[] = "Error general: " . $e->getMessage();
            return ['success' => false, 'saved_count' => 0, 'errors' => $errors];
        }
    }

}

