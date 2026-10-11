<?php

namespace backend\controllers;

use backend\helpers\RedisKeys;
use common\models\Business;
use backend\models\StandardRecipeIngredientForm;
use common\models\Menu;
use common\models\Convoy;
use common\models\MenuBundle;
use common\models\RecipeCategory;
use common\models\IngredientStock;
use common\models\RecipeStep;
use backend\helpers\ExcelHelper;
use yii\web\UploadedFile;
use common\models\UnitOfMeasurement;
use common\models\Category;
use Da\User\Traits\ContainerAwareTrait;
use Da\User\Validator\AjaxRequestModelValidator;
use rico\yii2images\models\Image;
use yii\helpers\Html;
use Symfony\Component\Yaml\Yaml;
use Yii;
use common\models\StandardRecipe;
use common\models\StandardRecipeSearch;
use common\models\MenuSearch;
use common\models\MonthlySales;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * StandardRecipeController implements the CRUD actions for StandardRecipe model.
 */
class StandardRecipeController extends Controller
{
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],

            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => [
                            'index'
                        ],
                        'allow' => true,
                        'roles' => ['recipe_list', 'recipe_view'],
                    ],
                    [
                        'actions' => [
                            'add-step',
                            'remove-step',
                            'create',
                            'delete-image',
                            'finish-recipe-creation',
                            'form-select-ingredient',
                            'select-ingredients',
                            'unselect-ingredient',
                            'duplicate-recipes',
                            'ckeck-title',
                            'download-recipes',
                            'download-recipes-pdf',
                            'download-complete-recipe-pdf',
                            'export-recipes-to-excel',
                            'export-recipes-plantilla',
                            'import-recipes',
                            'import-sub-recipes',
                            'edit-step',
                            'move-step',
                            'get-available-ingredients',
                            'get-sub-standard-recipes',
                            'import-sales-excel',
                            'download-sales-template',
                            'render-steps',
                            'render-special-steps',
                            'render-ingredients',
                            'ingredient-list'
                        ],
                        'allow' => true,
                        'roles' => [
                            'recipe_create'
                        ],
                    ],
                    [
                        'actions' => [
                            'add-step',
                            'remove-step',
                            'update',
                            'delete-image',
                            'finish-recipe-creation',
                            'form-select-ingredient',
                            'select-ingredients',
                            'unselect-ingredient',
                            'update-selected-ingredient',
                            'check-title',
                            'download-recipes',
                            'download-recipes-pdf',
                            'download-complete-recipe-pdf',
                            'export-recipes-to-excel',
                            'export-recipes-plantilla',
                            'import-recipes',
                            'import-sub-recipes',
                            'move-step',
                            'edit-step',
                            'get-available-ingredients',
                            'get-sub-standard-recipes',
                            'import-sales-excel',
                            'download-sales-template',
                            'render-steps',
                            'render-special-steps',
                            'render-ingredients',
                            'ingredient-list'
                        ],
                        'allow' => true,
                        'roles' => [
                            'recipe_update'
                        ],
                    ],
                    [
                        'actions' => [
                            'delete',
                        ],
                        'allow' => true,
                        'roles' => [
                            'recipe_delete'
                        ],
                    ],
                    [
                        'actions' => [
                            'analytics',
                        ],
                        'allow' => true,
                        'roles' => [
                            'menu_analysis_view',
                        ],
                    ],
                    [
                        'actions' => [
                            'charts',
                        ],
                        'allow' => true,
                        'roles' => [
                            'charts_view',
                        ],
                    ],
                    [
                        'actions' => [
                            'charts',
                        ],
                        'allow' => true,
                        'roles' => [
                            'charts_view',
                        ],
                    ],
                    [
                        'actions' => [
                            'matrix_bcg',
                        ],
                        'allow' => true,
                        'roles' => [
                            'matrix_bcg',
                        ],
                    ],
                    [
                        'actions' => [
                            'menu-improvement',
                        ],
                        'allow' => true,
                        'roles' => [
                            'menu_improvements_view',
                        ],
                    ],
                    [
                        'actions' => [
                            'menu-recipes',
                        ],
                        'allow' => true,
                        'roles' => [
                            'menu_view',
                        ],
                    ],
                    [
                        'actions' => [
                            'profit-comparison',
                        ],
                        'allow' => true,
                        'roles' => [
                            'profitability_view',
                        ],
                    ],
                    [
                        'actions' => [
                            'real-yield',
                            'export-real-yield-excel',
                        ],
                        'allow' => true,
                        'roles' => [
                            'real_profitability_view',
                        ],
                    ],
                    [
                        'actions' => [
                            'theoretical-yield',
                            'export-theoretical-yield-excel',
                        ],
                        'allow' => true,
                        'roles' => [
                            'theoretical_profitability_view',
                        ],
                    ],                    [
                        'actions' => [
                            'sales',
                        ],
                        'allow' => true,
                        'roles' => [
                            'sales_view',
                        ],
                    ],
                    [
                        'actions' => [
                            'save-sales',
                            'save-monthly-sales',
                        ],
                        'allow' => true,
                        'roles' => [
                            'sales_update',
                        ],
                    ],
                    [
                        'actions' => [
                            'select-unselect-for-menu',
                        ],
                        'allow' => true,
                        'roles' => [
                            'menu_add_item',
                            'menu_remove_item',
                        ],
                    ],
                    [
                        'actions' => [
                            'matrix-bcg',
                        ],
                        'allow' => true,
                        'roles' => [
                            'matrix_bcg',
                        ],
                    ],

                ],
            ],
            'backupReminder' => [
                'class' => \backend\components\BackupReminderBehavior::class,
            ],
        ];
    }

    /**
     * Lists all StandardRecipe models.
     * @return mixed
     */
    public function actionIndex($type = StandardRecipe::STANDARD_RECIPE_TYPE_MAIN)
    {
        $page = (int)Yii::$app->request->get('page', 1);
        // Obtener el valor de la cookie si existe
        $savedPageSize = (int)Yii::$app->request->cookies->getValue('recipe_page_size', 10);
        
        // Personalizar elementos por página - solo si viene en la URL
        $perPage = Yii::$app->request->get('per-page');
        
        // Si perPage no viene en la URL o no es válido, usar el valor guardado en la cookie
        if (!$perPage || !in_array((int)$perPage, [10, 25, 50, 100])) {
            $perPage = $savedPageSize;
        } else {
            // Solo guardar una nueva cookie si el valor es diferente al que ya tenemos
            if ((int)$perPage !== $savedPageSize) {
                $cookie = new \yii\web\Cookie([
                    'name' => 'recipe_page_size',
                    'value' => (int)$perPage,
                    'expire' => time() + 86400 * 30,
                ]);
                Yii::$app->response->cookies->add($cookie);
            }
        }
        
        // Usar perPage como la cantidad de elementos por página
        $pageSize = (int)$perPage;
        
        // Incluir siempre el per-page en la URL recordada
        Url::remember(['standard-recipe/index', 'type' => $type, 'page' => $page, 'per-page' => $pageSize], 'index-recipe');
        $business = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        $searchModel = new StandardRecipeSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->pagination->pageSize = $pageSize;
        $dataProvider->query->andWhere([
            'business_id' => $business['id'],
            'in_construction' => 0,
            'type' => $type
        ]);
        $ingredientCount = [];
        $recipes = $dataProvider->getModels();
        foreach ($recipes as $recipe) {
            $ingredientCount[$recipe->id] = [
                'ingredientCount' => $recipe->getIngredientRelations()->count(),
                'sub_recipe' => $recipe->getSubStandardRecipes()->count(),
            ];
        }
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'ingredientCount' => $ingredientCount
        ]);
    }    
    public function actionTheoreticalYield()
    {
        $business = RedisKeys::getBusiness();

            $theoreticalYieldData = $business->getTheoreticalYield();

            // Prepare lightweight objects with scalar values to avoid repeated getters and N+1 queries
            $preparedData = [];
            $allRecipeIds = [];
            foreach ($theoreticalYieldData['data'] as $cat) {
                foreach ($cat['recipes'] as $r) {
                    if (!empty($r->id)) $allRecipeIds[] = $r->id;
                }
            }

            // Eager-load ingredient relations for all recipes in one query (if any)
            $recipesMap = [];
            $recipesByCategory = [];
            $combosByCategory = [];
            // Build category name list for fallback batch query
            $categoryNames = array_map(function($c){ return $c['category']->name; }, $theoreticalYieldData['data']);

            if (!empty($allRecipeIds)) {
                $loaded = \common\models\StandardRecipe::find()
                    ->where(['id' => $allRecipeIds])
                    ->with(['ingredientRelations.ingredient', 'convoy'])
                    ->indexBy('id')
                    ->all();
                foreach ($loaded as $id => $model) {
                    $recipesMap[$id] = $model;
                }
            } else {
                // Fallback: batch load all recipes that belong to the categories returned by Business
                if (!empty($categoryNames)) {
                    $loaded = \common\models\StandardRecipe::find()
                        ->where([
                            'business_id' => $business->id,
                            'in_construction' => 0,
                            'type' => \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_MAIN,
                            'in_menu' => true,
                            // type_of_recipe IN categoryNames
                        ])
                        ->andWhere(['in', 'type_of_recipe', $categoryNames])
                        ->with(['ingredientRelations.ingredient', 'convoy'])
                        ->all();
                    foreach ($loaded as $model) {
                        $recipesMap[$model->id] = $model;
                        $recipesByCategory[$model->type_of_recipe][] = $model;
                    }
                }
                // Load combos (menus) once and group by category name
                $loadedCombos = \common\models\Menu::find()
                    ->where(['business_id' => $business->id, 'in_menu' => true])
                    ->with(['category'])
                    ->all();
                foreach ($loadedCombos as $menu) {
                    $catName = $menu->category->name ?? null;
                    if ($catName) $combosByCategory[$catName][] = $menu;
                }
            }

            foreach ($theoreticalYieldData['data'] as $cat) {
                // use an associative array so the view can access $category['recipes'] as before
                $c = [
                    'category' => $cat['category'],
                    'recipes' => [],
                    'combos' => [],
                ];

                // decide source: use Business-provided list if present, otherwise use batch-loaded maps
                $sourceRecipes = !empty($cat['recipes']) ? $cat['recipes'] : ($recipesByCategory[$cat['category']->name] ?? []);
                foreach ($sourceRecipes as $recipe) {
                    // if recipe is a model or AR proxy, get its id
                    $id = is_object($recipe) && isset($recipe->id) ? $recipe->id : null;
                    $model = ($id && isset($recipesMap[$id])) ? $recipesMap[$id] : $recipe;
                    $obj = new \stdClass();
                    $obj->id = $model->id;
                    $obj->title = $model->title;
                    $obj->recipeLastPrice = $model->recipeLastPrice;
                    $obj->price = $model->price;
                    $obj->costPercent = $model->getCostPercent();
                    $obj->is_food = $model->is_food;
                    $c['recipes'][] = $obj;
                }

                $sourceCombos = !empty($cat['combos']) ? $cat['combos'] : ($combosByCategory[$cat['category']->name] ?? []);
                foreach ($sourceCombos as $combo) {
                    $obj = new \stdClass();
                    $obj->id = $combo->id;
                    $obj->title = $combo->title ?? $combo->name;
                    $obj->cost = $combo->total_cost ?? $combo->totalCostByLastPrice ?? $combo->total_cost_last_price;
                    $obj->total_price = $combo->total_price ?? $combo->price ?? (method_exists($combo, 'getPrice') ? $combo->getPrice() : null);
                    $obj->costPercent = method_exists($combo, 'getCostPercent') ? $combo->getCostPercent() : ($combo->cost_percent_last_price ?? 0);
                    $c['combos'][] = $obj;
                }

                $preparedData[] = $c;
            }

            return $this->render('theoretical_yield', [
                'data' => $preparedData,
                'totalCost' => $theoreticalYieldData['totalCost'],
                'theoricalTotal' => $theoreticalYieldData['theoricalTotal'],
                'month' => $theoreticalYieldData['month'],
                'year' => $theoreticalYieldData['year'],
                'recipesByType' => $theoreticalYieldData['recipesByType'],
            ]);
        }
    
    /**
     * Displays real yield data for recipes and combos for a specific month and year
     * @param int|null $month Month (1-12, defaults to current month)
     * @param int|null $year Year (defaults to current year)
     * @return mixed
     */
    public function actionRealYield($month = null, $year = null)
    {
        $business = RedisKeys::getBusiness();
        
        // Si no se especifica mes, usar el actual
        if ($month === null) {
            $month = (int)date('n');
        }
        
        // Si no se especifica año, usar el actual
        if ($year === null) {
            $year = (int)date('Y');
        }

        $realYieldData = $business->getRealYield($month, $year);
        
        return $this->render('real_yield', [
            'data' => $realYieldData['data'],
            'totalPcr' => $realYieldData['totalPcr'],
            'totalSales' => $realYieldData['totalSales'],
            'month' => $realYieldData['month'],
            'year' => $realYieldData['year'],
            'recipesByType' => $realYieldData['recipesByType'],
        ]);
    }

    public function actionExportRealYieldExcel($month = null, $year = null)
    {
        $business = RedisKeys::getBusiness();

        // Si no se especifica mes, usar el actual
        if ($month === null) {
            $month = (int)date('n');
        }

        // Si no se especifica año, usar el actual
        if ($year === null) {
            $year = (int)date('Y');
        }

        $realYieldData = $business->getRealYield($month, $year);

        // Crear el archivo Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rentabilidad Real');

        // Configurar estilos
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4CAF50'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];

        $categoryStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2196F3'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];

        $dataStyle = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];

        // Configurar anchos de columna
        $sheet->getColumnDimension('A')->setWidth(40); // Nombre
        $sheet->getColumnDimension('B')->setWidth(15); // Costo
        $sheet->getColumnDimension('C')->setWidth(15); // Precio
        $sheet->getColumnDimension('D')->setWidth(20); // Costo %
        $sheet->getColumnDimension('E')->setWidth(15); // Ventas
        $sheet->getColumnDimension('F')->setWidth(15); // % Ventas
        $sheet->getColumnDimension('G')->setWidth(15); // Tipo

        // Alinear columna A (nombres) a la izquierda
        $sheet->getStyle('A:A')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        // Agregar información general
        $sheet->setCellValue('A1', 'Rentabilidad Real');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Información del período
        $months = [
            '1' => 'Enero', '2' => 'Febrero', '3' => 'Marzo', '4' => 'Abril', '5' => 'Mayo', '6' => 'Junio',
            '7' => 'Julio', '8' => 'Agosto', '9' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
        ];
        $sheet->setCellValue('A2', 'Período: ' . $months[$month] . ' ' . $year);
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Total de ventas
        $sheet->setCellValue('A3', 'Total de ventas: ' . number_format($realYieldData['totalSales'], 2));
        $sheet->mergeCells('A3:G3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Rentabilidad total
        if ($realYieldData['totalPcr'] !== null) {
            $totalPcrValue = is_numeric($realYieldData['totalPcr'])
                ? (float)$realYieldData['totalPcr']
                : (float)str_replace(['%', ','], ['', '.'], $realYieldData['totalPcr']);
            $sheet->setCellValue('A4', 'Rentabilidad Total: ' . number_format($totalPcrValue * 100, 2) . '%');
            $sheet->mergeCells('A4:G4');
            $sheet->getStyle('A4')->getFont()->setBold(true);
            $sheet->getStyle('A4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        // Fecha de generación
        $sheet->setCellValue('A5', 'Fecha de generación: ' . date('d/m/Y'));
        $sheet->mergeCells('A5:G5');
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Headers de la tabla
        $sheet->setCellValue('A7', 'Nombre');
        $sheet->setCellValue('B7', 'Costo');
        $sheet->setCellValue('C7', 'Precio');
        $sheet->setCellValue('D7', 'Costo %');
        $sheet->setCellValue('E7', 'Ventas');
        $sheet->setCellValue('F7', '% Ventas');
        $sheet->setCellValue('G7', 'Tipo');

        $sheet->getStyle('A7:G7')->applyFromArray($headerStyle);

        $row = 8; // Comenzar después de los headers

        foreach ($realYieldData['data'] as $category) {
            // Calcular CPR de la categoría usando los datos actuales con ventas históricas
            $categoryTotalPcr = 0;
            foreach ($category['recipes'] as $recipe) {
                $categoryTotalPcr += $recipe->getCpr($realYieldData['totalSales']);
            }
            foreach ($category['combos'] as $combo) {
                $categoryTotalPcr += $combo->getCpr($realYieldData['totalSales']);
            }

            // Agregar fila de categoría
            $sheet->setCellValue('A'.$row, $category['category']->name . ': ' . number_format($categoryTotalPcr * 100, 2) . '%');
            $sheet->mergeCells('A'.$row.':G'.$row);
            $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray($categoryStyle);
            $row++;

            // Agregar recetas
            foreach ($category['recipes'] as $recipe) {
                $costPercentValue = is_numeric($recipe->costPercent)
                    ? (float)$recipe->costPercent
                    : (float)str_replace(['%', ','], ['', '.'], $recipe->costPercent);

                $sheet->setCellValue('A'.$row, $recipe->title);
                $sheet->setCellValue('B'.$row, number_format((float)$recipe->cost, 2));
                $sheet->setCellValue('C'.$row, number_format((float)$recipe->price, 2));
                $sheet->setCellValue('D'.$row, number_format($costPercentValue * 100, 2) . '%');
                $sheet->setCellValue('E'.$row, number_format((float)$recipe->sales, 2));
                $sheet->setCellValue('F'.$row, number_format($recipe->getSalesPercent($realYieldData['totalSales']) * 100, 2) . '%');
                $sheet->setCellValue('G'.$row, $recipe->is_food ? 'Alimento' : 'Bebida');

                $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray($dataStyle);
                $row++;
            }

            // Agregar combos
            foreach ($category['combos'] as $combo) {
                $costPercentValue = is_numeric($combo->costPercent)
                    ? (float)$combo->costPercent
                    : (float)str_replace(['%', ','], ['', '.'], $combo->costPercent);

                $sheet->setCellValue('A'.$row, $combo->title);
                $sheet->setCellValue('B'.$row, number_format((float)$combo->cost, 2));
                $sheet->setCellValue('C'.$row, number_format((float)$combo->total_price, 2));
                $sheet->setCellValue('D'.$row, number_format($costPercentValue * 100, 2) . '%');
                $sheet->setCellValue('E'.$row, number_format((float)$combo->sales, 2));
                $sheet->setCellValue('F'.$row, number_format($combo->getSalesPercent($realYieldData['totalSales']) * 100, 2) . '%');
                $sheet->setCellValue('G'.$row, 'Combo');

                $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray($dataStyle);
                $row++;
            }

            // Agregar fila vacía entre categorías
            $row++;
        }

        // Formato de moneda para columnas B y C
        $lastRow = $row - 1;
        $sheet->getStyle('B8:B'.$lastRow)->getNumberFormat()->setFormatCode('$#,##0.00');
        $sheet->getStyle('C8:C'.$lastRow)->getNumberFormat()->setFormatCode('$#,##0.00');

        // Crear y enviar el archivo
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'Rentabilidad_Real_' . $months[$month] . '_' . $year . '_' . date('Y-m-d_H-i-s') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($tempFile);

        return Yii::$app->response->sendFile($tempFile, $filename);
    }


    /**
     * Displays a single StandardRecipe model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new StandardRecipe model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($type = StandardRecipe::STANDARD_RECIPE_TYPE_MAIN)
    {
        $user = Yii::$app->user->identity;
        if ($type == StandardRecipe::STANDARD_RECIPE_TYPE_MAIN && $user->hasRestrictions('recipes')) {
            Yii::$app->session->setFlash('warning', "Haz alcanzado el límite de recetas");
            return $this->redirect(['standard-recipe/index']);
        }
        if ($type == StandardRecipe::STANDARD_RECIPE_TYPE_SUB && $user->hasRestrictions('subrecipes')) {
            Yii::$app->session->setFlash('warning', "Haz alcanzado el límite de subrecetas");
            return $this->redirect(['sub-standard-recipe/index']);
        }
        Url::remember(['standard-recipe/create', 'type' => $type], 'create-recipe');
        $business = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        $model = StandardRecipe::findOne(['business_id' => $business['id'], 'in_construction' => true, 'type' => $type]);
        if (empty($model)) {
            $model = new StandardRecipe([
                'business_id' => $business['id'],
                'type' => $type,
                'title' => '',
                'in_construction' => true,
            ]);
        } elseif($model->in_construction){
            $model->title = ''; 
        }
//        elseif($model->in_construction){
//            Yii::$app->db->createCommand()
//                ->delete("ingredient_standard_recipe", ['standard_recipe_id' => $model->id])
//                ->execute();
//            Yii::$app->db->createCommand()
//                ->delete("standard_recipe_sub_standard_recipe", ['standard_recipe_id' => $model->id])
//                ->execute();
//        }

        if (Yii::$app->request->isGet) {
            $model->save(false);
        }

        $post = Yii::$app->request->post();
        if (!empty($post)) {
            $post['StandardRecipe']['in_construction'] = false;
        }

        if (array_key_exists('ajax', $post)) {
            $this->make(AjaxRequestModelValidator::class, [$model])->validate();
        }
        if ($model->load($post) && $model->save()) {
            // Procesar excludeFromCost y costPercentage
            $excludeFromCost = Yii::$app->request->post('excludeFromCost', []);
            $costPercentage = Yii::$app->request->post('costPercentage', []);

            // Actualizar relaciones de ingredientes
            foreach ($model->ingredientRelations as $relation) {
                $ingredientId = $relation->ingredient_id;

                // Actualizar exclude_from_cost
                $relation->exclude_from_cost = isset($excludeFromCost[$ingredientId]);

                // Actualizar cost_percentage
                if (isset($costPercentage[$ingredientId])) {
                    $relation->cost_percentage = intval($costPercentage[$ingredientId]);
                } else {
                    $relation->cost_percentage = 0; // Valor por defecto
                }

                $relation->save(false); // Guardar sin validación
            }

            // --- Guardar pendientes de ingredientes ---
            $pendingIngredients = Yii::$app->request->post('pendingFieldsRecipeIngredients', '');
            \common\models\PendingField::deleteAll([
                'model_type' => 'recipe_ingredient',
                'model_id' => $model->id
            ]);
            if (!empty($pendingIngredients)) {
                $pendingIngredientsArr = @json_decode($pendingIngredients, true);
                if (is_array($pendingIngredientsArr)) {
                    $now = time();
                    foreach ($pendingIngredientsArr as $pending) {
                        if (!empty($pending['ingredient_id']) && !empty($pending['field'])) {
                            $isRecipe = isset($pending['is_recipe']) ? $pending['is_recipe'] : 0;
                            $pf = new \common\models\PendingField();
                            $pf->model_type = 'recipe_ingredient';
                            $pf->model_id = $model->id;
                            $pf->field = $pending['ingredient_id'] . ':' . $pending['field'] . ':' . $isRecipe;
                            $pf->created_at = $now;
                            $pf->updated_at = $now;
                            $pf->save(false);
                        }
                    }
                }
            }

            if ($model->type == $model::STANDARD_RECIPE_TYPE_MAIN) {
                return $this->redirect(['standard-recipe/index', 'type' => $model->type]);
            } else {
                return $this->redirect(['sub-standard-recipe/index']);
            }
        } elseif ($model->hasErrors()) {
            // Errors are shown inline in the form via AJAX validation
        }
        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionSelectIngredients($id)
    {
        $this->layout = 'blank';
        $model = $this->findModel($id);
        $form = new StandardRecipeIngredientForm();

        $post = Yii::$app->request->post();
        if (array_key_exists('ajax', $post)) {
            $this->make(AjaxRequestModelValidator::class, [$form])->validate();
        }
        if ($form->load($post) && $form->validate()) {
            $force = !empty($post['force']);

            if (empty($form->subRecipeId)) {
                $isLinked = $model->getIngredientRelations()
                    ->where(['ingredient_id' => $form->ingredientId])
                    ->exists();
                if ($isLinked && !$force) {
                    return $this->asJson([
                        'success' => false,
                        'isDuplicate' => true,
                        'message' => Yii::t('app', 'Este ingrediente ya está agregado. Debe modificar su cantidad desde la lista de ingredientes.')
                    ]);
                }
                $model->addUpdateIngredient($form->ingredientId, $form->quantity);
            } else {
                if ($model->wouldCreateCircularDependency((int)$form->subRecipeId)) {
                    return $this->asJson([
                        'success' => false,
                        'errors' => ['subRecipeId' => [Yii::t('app', 'No se puede agregar esta subreceta porque crearía una dependencia circular.')]]
                    ]);
                }
                $isLinked = $model->getSubStandardRecipes()
                    ->andWhere(['standard_recipe.id' => $form->subRecipeId])
                    ->exists();
                if ($isLinked && !$force) {
                    return $this->asJson([
                        'success' => false,
                        'isDuplicate' => true,
                        'message' => Yii::t('app', 'Esta subreceta ya está agregada. ¿Desea modificar la cantidad?')
                    ]);
                }
                $model->addUpdateSubRecipe($form->subRecipeId, $form->quantity);
            }
            
            if (Yii::$app->request->isAjax) {
                return $this->asJson([
                    'success' => true,
                    'message' => Yii::t('app', 'Ingrediente agregado exitosamente')
                ]);
            }
        }

        if (Yii::$app->request->isAjax) {
            return $this->asJson([
                'success' => false,
                'errors' => $form->errors
            ]);
        }

        return $this->asJson(['success' => false]);
    }

    public function actionFormSelectIngredient($id = null)
    {
        $recipe = StandardRecipe::findOne(['id' => $id]);
        return $this->renderAjax('create/_form_ingredient', [
            'model' => new \backend\models\StandardRecipeIngredientForm(),
            'recipe' => $recipe
        ]);
    }    public function actionImportRecipes($id)
    {
        $business = Business::findOne(['id' => $id]);

        $file = UploadedFile::getInstanceByName('ingredient-file');

        if ($file) {
            $result = ExcelHelper::importRecipe($business, $file->tempName);
            
            if (!$result['success']) {
                Yii::$app->session->setFlash('import_errors', $result['errors']);
            } else {
                Yii::$app->session->setFlash('success', "Importación completada exitosamente. {$result['saved_count']} recetas importadas.");
            }
        }

        return $this->redirect(['standard-recipe/index']);
    }
    public function actionImportSubRecipes($id)
    {
        $business = Business::findOne(['id' => $id]);

        $file = UploadedFile::getInstanceByName('ingredient-file');

        if ($file) {
            $result = ExcelHelper::importSubRecipe($business, $file->tempName);
            if (!$result['success']) {
                // Mostrar errores en flash message
                Yii::$app->session->setFlash('import_errors', $result['errors']);
            } else {
                // Mostrar mensaje de éxito
                Yii::$app->session->setFlash('success', "Se importaron {$result['saved_count']} subrecetas correctamente.");
            }
        }

        return $this->redirect(['sub-standard-recipe/index']);
    }

    public function actionUpdateSelectedIngredient($id, $ingredientId, $isRecipe = false)
{
    $model = $this->findModel($id);

    $quantity = Yii::$app->request->post('quantity');
    $newItemId = Yii::$app->request->post('newItemId', $ingredientId);
    $newIsRecipe = Yii::$app->request->post('isRecipe', $isRecipe); // Usar el tipo del nuevo elemento

    // Si el ingrediente/subreceta ha cambiado
    if ($newItemId != $ingredientId || $newIsRecipe != $isRecipe) {
        // Eliminar el ingrediente/subreceta actual
        if ($isRecipe) {
            $model->removeSubRecipe($ingredientId);
        } else {
            $model->removeIngredient($ingredientId);
        }
        // Agregar el nuevo elemento según su tipo
        if ($newIsRecipe) {
            if ($model->wouldCreateCircularDependency((int)$newItemId)) {
                return $this->asJson([
                    'success' => false,
                    'error' => Yii::t('app', 'No se puede agregar esta subreceta porque crearía una dependencia circular.')
                ]);
            }
            $model->addUpdateSubRecipe($newItemId, $quantity);
        } else {
            $model->addUpdateIngredient($newItemId, $quantity);
        }
    } else {
        // Actualizar solo la cantidad
        if ($isRecipe) {
            $model->addUpdateSubRecipe($ingredientId, $quantity);
        } else {
            $model->addUpdateIngredient($ingredientId, $quantity);
        }
    }

    return $this->asJson(['success' => true]);
}
    public function actionGetAvailableIngredients()
{
    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    $business = RedisKeys::getBusiness();
    
    $ingredients = IngredientStock::find()
        ->select(['id', 'ingredient as name', 'portion_um as um'])
        ->where(['business_id' => $business->id])
        ->orderBy(['ingredient' => SORT_ASC])
        ->asArray()
        ->all();
    
    return [
        'success' => true,
        'ingredients' => $ingredients
    ];
}
public function actionGetSubStandardRecipes()
{
    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    $businessData = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
    $business = Business::findOne(['id' => $businessData['id']]);
    $subrecipes = StandardRecipe::find()
        ->where([
            'business_id' => $business->id, 
            'type' => StandardRecipe::STANDARD_RECIPE_TYPE_SUB
        ])
        ->select(['id', 'title', 'um'])
        ->orderBy(['title' => SORT_ASC])
        ->asArray()
        ->all();
    
    return $subrecipes;
    
}

    public function actionUnselectIngredient($id, $ingredientId, $isRecipe = false)
    {
        $model = $this->findModel($id);

        if ($isRecipe) {
            $model->removeSubRecipe($ingredientId);
        } else {
            $model->removeIngredient($ingredientId);
        }

        if (Yii::$app->request->isAjax) {
            return $this->asJson([
                'success' => true,
                'message' => Yii::t('app', 'Ingrediente eliminado exitosamente')
            ]);
        }

        return $this->asJson(['success' => false]);
    }

    public function actionFinishRecipeCreation($id)
    {
        $this->layout = 'blank';
        $model = $this->findModel($id);

        $post = Yii::$app->request->post();
        if (array_key_exists('ajax', $post)) {
            $this->make(AjaxRequestModelValidator::class, [$model])->validate();
        }

        if ($model->load($post) && $model->save(true, ['flowchart', 'equipment', 'steps', 'allergies'])) {
            return $this->redirect(['index']);
        }

        return $this->render('create/_finish_creation', [
            'model' => $model
        ]);
    }

    public function actionAddStep($id)
    {
        $model = $this->findModel($id);
        $step = new RecipeStep([
            'recipe_id' => $model->id,
        ]);

        $post = Yii::$app->request->post();
        Yii::info(['POST recibido' => $post], 'debug.step');

        // Si los campos de tiempo existen en el POST, combínalos
        $h = isset($post['input-hours']) ? str_pad($post['input-hours'], 2, '0', STR_PAD_LEFT) : null;
        $m = isset($post['input-minutes']) ? str_pad($post['input-minutes'], 2, '0', STR_PAD_LEFT) : null;
        $s = isset($post['input-seconds']) ? str_pad($post['input-seconds'], 2, '0', STR_PAD_LEFT) : null;
        if ($h !== null && $m !== null && $s !== null) {
            $post['RecipeStep']['time'] = "$h:$m:$s";
            Yii::info(['Tiempo combinado' => $post['RecipeStep']['time']], 'debug.step');
        }
        // Si N/A está marcado, ignorar el tiempo
        if (!empty($post['time_na'])) {
            $post['RecipeStep']['time'] = null;
        }

        $okLoad = $step->load($post);
        Yii::info(['okLoad' => $okLoad, 'step->attributes' => $step->attributes], 'debug.step');
        if ($okLoad) {
            if ($step->validate()) {
                Yii::info(['VALIDADO' => true], 'debug.step');
                if ($step->save()) {
                    Yii::info(['GUARDADO' => true, 'step->attributes' => $step->attributes], 'debug.step');
                    return $this->asJson([
                        'success' => true,
                        'message' => Yii::t('app', 'Paso agregado exitosamente'),
                        'closeModal' => true,
                        'stepId' => $step->id
                    ]);
                } else {
                    Yii::info(['GUARDADO' => false, 'errors' => $step->errors], 'debug.step');
                    return $this->asJson([
                        'success' => false,
                        'errors' => $step->errors,
                        'message' => 'Error al guardar el paso en la base de datos'
                    ]);
                }
            } else {
                Yii::info(['VALIDADO' => false, 'errors' => $step->errors], 'debug.step');
                return $this->asJson([
                    'success' => false,
                    'errors' => $step->errors,
                    'message' => 'Los datos del formulario no son válidos'
                ]);
            }
        } else {
            Yii::info(['LOAD' => false, 'step->attributes' => $step->attributes], 'debug.step');
            return $this->asJson([
                'success' => false,
                'errors' => $step->errors,
                'message' => 'No se pudieron cargar los datos del formulario',
                'postData' => $post // Para debug
            ]);
        }
    }

    public function actionRemoveStep($recipeId, $id)
    {
        $model = $this->findModel($recipeId);
        $step = RecipeStep::findOne(['id' => $id]);

        if (!empty($step)) {
            $step->delete();
            
            if (Yii::$app->request->isAjax) {
                return $this->asJson([
                    'success' => true,
                    'message' => Yii::t('app', 'Paso eliminado exitosamente')
                ]);
            }
        }

        if (Yii::$app->request->isAjax) {
            return $this->asJson([
                'success' => false,
                'message' => Yii::t('app', 'No se pudo eliminar el paso')
            ]);
        }

        $previous = '';
        if ($model->in_construction) {
            $previous = 'create-recipe';
        } else {
            $previous = 'update-recipe';
        }
        return $this->asJson(['success' => false]);
    }

    /**
     * Updates an existing StandardRecipe model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        Url::remember(['standard-recipe/update', 'id' => $id], 'update-recipe');
        $model = $this->findModel($id);

        $post = Yii::$app->request->post();
        if (array_key_exists('ajax', $post)) {
            $this->make(AjaxRequestModelValidator::class, [$model])->validate();
        }

        if ($model->load($post) && $model->save()) {
            // Procesar excludeFromCost y costPercentage
            $excludeFromCost = Yii::$app->request->post('excludeFromCost', []);
            $costPercentage = Yii::$app->request->post('costPercentage', []);

            // Actualizar relaciones de ingredientes
            foreach ($model->ingredientRelations as $relation) {
                $ingredientId = $relation->ingredient_id;

                // Actualizar exclude_from_cost
                $relation->exclude_from_cost = isset($excludeFromCost[$ingredientId]);

                // Actualizar cost_percentage
                if (isset($costPercentage[$ingredientId])) {
                    $relation->cost_percentage = intval($costPercentage[$ingredientId]);
                } else {
                    $relation->cost_percentage = 0; // Valor por defecto
                }

                $relation->save(false); // Guardar sin validación
            }

            // --- Guardar pendientes de ingredientes ---
            $pendingIngredients = Yii::$app->request->post('pendingFieldsRecipeIngredients', '');
            \common\models\PendingField::deleteAll([
                'model_type' => 'recipe_ingredient',
                'model_id' => $model->id
            ]);
            if (!empty($pendingIngredients)) {
                $pendingIngredientsArr = @json_decode($pendingIngredients, true);
                if (is_array($pendingIngredientsArr)) {
                    $now = time();
                    foreach ($pendingIngredientsArr as $pending) {
                        if (!empty($pending['ingredient_id']) && !empty($pending['field'])) {
                            $isRecipe = isset($pending['is_recipe']) ? $pending['is_recipe'] : 0;
                            $pf = new \common\models\PendingField();
                            $pf->model_type = 'recipe_ingredient';
                            $pf->model_id = $model->id;
                            $pf->field = $pending['ingredient_id'] . ':' . $pending['field'] . ':' . $isRecipe;
                            $pf->created_at = $now;
                            $pf->updated_at = $now;
                            $pf->save(false);
                        }
                    }
                }
            }
            if ($model->type == $model::STANDARD_RECIPE_TYPE_SUB) {
                return $this->redirect(['sub-standard-recipe/index']);
            }

            return $this->redirect(Url::previous('index-recipe'));
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing StandardRecipe model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete()
    {
        if (Yii::$app->request->isPost) {
            $ids = Yii::$app->request->post('keys'); // Recibir los IDs enviados desde el frontend
    
            if ($ids === 'all') {
                // Delete all recipes
                $businessData = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
                $business = Business::findOne(['id' => $businessData['id']]);
                StandardRecipe::deleteAll([
                    'business_id' => $business->id,
                    'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN
                ]);
                return $this->asJson(['success' => true]);
            } else if (!empty($ids)) {
                // Delete selected recipes
                foreach ($ids as $id) {
                    $model = $this->findModel($id);
                    if ($model) {
                        $model->delete(); 
                    }
                }
                return $this->asJson(['success' => true]);
            }
        }
    }

    public function actionDeleteImage($id)
    {
        $post = Yii::$app->request->post();
        if (isset($post['key'])) {
            $imageId = $post['key'];
            $model = $this->findModel($id);

            $images = $model->getImages();
            foreach ($images as $image) {
                if ($image->getPrimaryKey() == $imageId) {
                    $model->removeImage($image);
                    break;
                }
            }

            return $this->asJson([]);
        }

        return $this->asJson(['error' => true, 'data' => $post]);
    }    
    public function actionSales()
    {
        $startDate = Yii::$app->request->get('start_date');
        $endDate = Yii::$app->request->get('end_date');
        $month = Yii::$app->request->get('month', date('n')); // Si no se especifica mes, usar el mes actual
        $year = Yii::$app->request->get('year', date('Y')); // Si no se especifica año, usar el año actual
        
        $business = RedisKeys::getBusinessData();        // Create search models for each type
        $foodSearchModel = new StandardRecipeSearch();
        $drinkSearchModel = new class extends StandardRecipeSearch {
            public function formName()
            {
                return 'StandardRecipeSearchDrink';
            }
        };
        $comboSearchModel = new MenuSearch();

        // Base query for food recipes
        $foodQuery = StandardRecipe::find()->where([
            'business_id' => $business['id'],
            'in_construction' => 0,
            'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN,
            'in_menu' => true,
            'is_food' => true,
        ]);

        // Base query for drink recipes
        $drinkQuery = StandardRecipe::find()->where([
            'business_id' => $business['id'],
            'in_construction' => 0,
            'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN,
            'in_menu' => true,
            'is_food' => false,
        ]);

        // Base query for combos
        $comboQuery = Menu::find()->where([
            'business_id' => $business['id'],
            'in_menu' => true,
        ]);

        // Add date filtering if dates are provided
        if ($startDate && $endDate) {
            $foodQuery->andWhere(['between', 'date', $startDate, $endDate]);
            $drinkQuery->andWhere(['between', 'date', $startDate, $endDate]);
            $comboQuery->andWhere(['between', 'date', $startDate, $endDate]);
        }        // Create data providers with search functionality
        $foodDataProvider = new ActiveDataProvider([
            'query' => $foodQuery,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        $drinkDataProvider = new ActiveDataProvider([
            'query' => $drinkQuery,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        $comboDataProvider = new ActiveDataProvider([
            'query' => $comboQuery,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);        // Apply search filters using different parameter names to avoid conflicts
        $params = Yii::$app->request->queryParams;
        
        // Food search - usar parámetro personalizado para evitar conflictos
        if (isset($params['food_title'])) {
            $foodSearchModel->title = $params['food_title'];
            $foodDataProvider->query->andFilterWhere(['like', 'title', $params['food_title']]);
        }

        // Drink search - crear parámetros manuales para evitar conflictos
        if (isset($params['drink_title'])) {
            $drinkSearchModel->title = $params['drink_title'];
            $drinkDataProvider->query->andFilterWhere(['like', 'title', $params['drink_title']]);
        }

        // Combo search - usar parámetro personalizado para evitar conflictos
        if (isset($params['combo_name'])) {
            $comboSearchModel->name = $params['combo_name'];
            $comboDataProvider->query->andFilterWhere(['like', 'name', $params['combo_name']]);
        }

        // Cargamos las ventas de cada receta desde la tabla mensual para el mes y año seleccionados
        $this->loadMonthlySalesForDataProvider($foodDataProvider, $month, $year, MonthlySales::TYPE_RECIPE);
        $this->loadMonthlySalesForDataProvider($drinkDataProvider, $month, $year, MonthlySales::TYPE_RECIPE);
        $this->loadMonthlySalesForDataProvider($comboDataProvider, $month, $year, MonthlySales::TYPE_MENU);

        return $this->render('sales', [
            'foodDataProvider' => $foodDataProvider,
            'drinkDataProvider' => $drinkDataProvider,
            'comboDataProvider' => $comboDataProvider,
            'foodSearchModel' => $foodSearchModel,
            'drinkSearchModel' => $drinkSearchModel,
            'comboSearchModel' => $comboSearchModel,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedMonth' => $month,
            'selectedYear' => $year
        ]);
    }/**
     * Carga los datos de ventas mensuales para cada modelo en un data provider
     * @param ActiveDataProvider $dataProvider Proveedor de datos a actualizar
     * @param int $month Mes (1-12)
     * @param int $year Año
     * @param string $modelType Tipo de modelo ('standard_recipe' o 'menu')
     */
    protected function loadMonthlySalesForDataProvider($dataProvider, $month, $year, $modelType)
    {
        $models = $dataProvider->getModels();
        if ($month == 0 && $year == 0) {
            // Buscar ventas de todos los años y todos los meses
            foreach ($models as $model) {
                $model->sales = MonthlySales::getSales($modelType, $model->id, null, null);
                $model->sales_month = null;
            }
            
        } elseif ((int)$month == 0) {
            // Buscar ventas de todos los meses para el año especificado
            foreach ($models as $model) {
                $model->sales = MonthlySales::getSales($modelType, $model->id, null, $year);
                $model->sales_month = null;
            }
        } elseif ((int)$year == 0) {
            // Buscar ventas de todos los años para el mes especificado
            foreach ($models as $model) {
                $model->sales = MonthlySales::getSales($modelType, $model->id, $month, null);
                $model->sales_month = $month;

            }
        } else {
            // Buscar ventas para el mes y año especificados
            foreach ($models as $model) {
                $model->sales = MonthlySales::getSales($modelType, $model->id, $month, $year);
                $model->sales_month = $month;
            }
        }
        $dataProvider->setModels($models);
    }

    /**
     * Guarda las ventas de una receta individual (acción AJAX para la vista antigua)
     * @param int $id ID de la receta
     */
    public function actionSaveSales($id)
    {
        $model = $this->findModel($id);
        $post = Yii::$app->request->post();
        $month = null;
        $sales = null;

        if (isset($post['StandardRecipe'])) {
            if (isset($post['StandardRecipe']['sales_month'])) {
                $month = $post['StandardRecipe']['sales_month'];
                $model->sales_month = $month;
            }
            if (isset($post['StandardRecipe']['sales'])) {
                $sales = $post['StandardRecipe']['sales'];
                $model->sales = $sales;
            }
        }

        // Si tenemos todos los datos, guardar en la tabla histórica también
        if ($month !== null && $sales !== null) {
            MonthlySales::saveSales(
                MonthlySales::TYPE_RECIPE,
                $model->id,
                $month,
                date('Y'), // Año actual
                $sales
            );
        }

        if ($model->save(false)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson([
            'success' => false,
            'errors' => array_values(array_values($model->errors))
        ]);
    }
      /**
     * Guarda las ventas de todas las recetas mostradas en la vista
     * (Acción para el nuevo botón "Guardar ventas")
     */
    public function actionSaveMonthlySales()
    {
        $post = Yii::$app->request->post();
        $month = isset($post['month']) ? (int)$post['month'] : (int)date('n');
        $year = isset($post['year']) ? (int)$post['year'] : (int)date('Y');
        $success = true;
        $errors = [];
        $savedItems = 0;
        
        // Validación básica de mes y año
        if ($month < 1 || $month > 12) {
            return $this->asJson([
                'success' => false,
                'errors' => ["Mes inválido: $month. Debe ser entre 1 y 12."]
            ]);
        }
        if ($year < 2000 || $year > 2100) {
            return $this->asJson([
                'success' => false,
                'errors' => ["Año inválido: $year. Debe ser entre 2000 y 2100."]
            ]);
        }
        
        // Procesar ventas de recetas (comida)
        if (isset($post['food']) && is_array($post['food'])) {
            foreach ($post['food'] as $recipeId => $sales) {
                // Validar que recipeId sea un número válido
                if (!is_numeric($recipeId) || (int)$recipeId <= 0) {
                    $errors[] = "ID de receta inválido: $recipeId";
                    continue;
                }
                
                if (!MonthlySales::saveSales(MonthlySales::TYPE_RECIPE, (int)$recipeId, $month, $year, (float)$sales)) {
                    $success = false;
                    $errors[] = "Error al guardar ventas de receta ID: $recipeId";
                } else {
                    $savedItems++;
                }
            }
        }
        
        // Procesar ventas de bebidas
        if (isset($post['drink']) && is_array($post['drink'])) {
            foreach ($post['drink'] as $recipeId => $sales) {
                // Validar que recipeId sea un número válido
                if (!is_numeric($recipeId) || (int)$recipeId <= 0) {
                    $errors[] = "ID de bebida inválido: $recipeId";
                    continue;
                }
                
                if (!MonthlySales::saveSales(MonthlySales::TYPE_RECIPE, (int)$recipeId, $month, $year, (float)$sales)) {
                    $success = false;
                    $errors[] = "Error al guardar ventas de bebida ID: $recipeId";
                } else {
                    $savedItems++;
                }
            }
        }
        
        // Procesar ventas de combos/menús
        if (isset($post['combo']) && is_array($post['combo'])) {
            foreach ($post['combo'] as $menuId => $sales) {
                // Validar que menuId sea un número válido
                if (!is_numeric($menuId) || (int)$menuId <= 0) {
                    $errors[] = "ID de combo inválido: $menuId";
                    continue;
                }
                
                if (!MonthlySales::saveSales(MonthlySales::TYPE_MENU, (int)$menuId, $month, $year, (float)$sales)) {
                    $success = false;
                    $errors[] = "Error al guardar ventas de combo ID: $menuId";
                } else {
                    $savedItems++;
                }
            }
        }
        
        Yii::info("Guardado de ventas mensuales completado. Mes: $month, Año: $year, Elementos guardados: $savedItems, Éxito: " . ($success ? 'Sí' : 'No'));
        
        return $this->asJson([
            'success' => $success,
            'savedItems' => $savedItems,
            'errors' => $errors
        ]);
    }

    public function actionMenuRecipes($bundle = null)
    {
        $categoryId = Yii::$app->request->get('categoryId', null);
        $sort = Yii::$app->request->get('sort', null);
        $order = Yii::$app->request->get('order', 'desc');
    
        $business = RedisKeys::getBusinessData();
    
        $bundleModel = null;
        if ($bundle !== null) {
            $bundleModel = MenuBundle::findOne(['id' => $bundle, 'business_id' => $business['id']]);
        }
    
        $category = null;
        if (!empty($categoryId)) {
            $category = RecipeCategory::find()
                ->where([
                    'business_id' => $business['id'],
                    'id' => $categoryId
                ])->one();
        }
        
        $title = Yii::$app->request->get('title');
    
        $page = (int)Yii::$app->request->get('page', 1);
        // Obtener el valor de la cookie si existe
        $savedPageSize = (int)Yii::$app->request->cookies->getValue('menu_recipes_page_size', 10);
        
        // Personalizar elementos por página - solo si viene en la URL
        $perPage = Yii::$app->request->get('per-page');
        
        // Si perPage no viene en la URL o no es válido, usar el valor guardado en la cookie
        if (!$perPage || !in_array((int)$perPage, [10, 25, 50, 100])) {
            $perPage = $savedPageSize;
        } else {
            // Solo guardar una nueva cookie si el valor es diferente al que ya tenemos
            if ((int)$perPage !== $savedPageSize) {
                $cookie = new \yii\web\Cookie([
                    'name' => 'menu_recipes_page_size',
                    'value' => (int)$perPage,
                    'expire' => time() + 86400 * 30,
                ]);
                Yii::$app->response->cookies->add($cookie);
            }
        }
        
        // Usar perPage como la cantidad de elementos por página
        $pageSize = (int)$perPage;
    
        Url::remember(['standard-recipe/menu-recipes', 'page' => $page, 'per-page' => $pageSize, 'bundle' => $bundle], 'menu-recipes');
    
        if ($bundleModel) {
            $bundleRecipesIds = $bundleModel->getStandardRecipes(true);
            $bundleCombosIds = $bundleModel->getCombos(true);
            $recipesFilter = [
                'business_id' => $business['id'],
                'in_construction' => 0,
                'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN,
                'in_menu' => true,
                'id' => $bundleRecipesIds,
            ];
    
            $combosFilter = [
                'business_id' => $business['id'],
                'in_menu' => true,
                'id' => $bundleCombosIds,
            ];
        } else {
            $recipesFilter = [
                'business_id' => $business['id'],
                'in_construction' => 0,
                'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN,
                'in_menu' => true,
            ];
    
            $combosFilter = [
                'business_id' => $business['id'],
                'in_menu' => true,
            ];
        }
    
        if($category){
            $recipesFilter['type_of_recipe'] = $category->name;
            $combosFilter['category_id'] = $category->id;
        }
        if (!empty($title)) {
            $recipesFilter = ['like', 'title', "%$title%", false];
            $combosFilter = ['like', 'name', "%$title%", false];
        }
        
        // Obtener datos sin aplicar ordenación en la consulta
        $totalRecipes = (int)StandardRecipe::find()->where($recipesFilter)->count();
        $recipes = StandardRecipe::find()->where($recipesFilter)->all();
        
        $totalCombos = (int)Menu::find()->where($combosFilter)->count();
        $combos = Menu::find()->where($combosFilter)->all();
        
        // Obtener los modelos para la lista de disponibles
        $recipesFilter['in_menu'] = false;
        $availableRecipes = StandardRecipe::find()->where($recipesFilter)->all();
        
        $combosFilter['in_menu'] = false;
        $availableCombos = Menu::find()->where($combosFilter)->all();
    
        // Combinar las recetas y los combos
        $models = array_merge($recipes, $combos);
        
        // Aplicar ordenación si es necesario
        if (!empty($sort)) {
            if ($sort == 'title') {
                usort($models, function($a, $b) use ($order) {
                    $aTitle = strtolower($a instanceof StandardRecipe ? $a->title : $a->name);
                    $bTitle = strtolower($b instanceof StandardRecipe ? $b->title : $b->name);
                    
                    if ($order == 'asc') {
                        return strcmp($aTitle, $bTitle);
                    } else {
                        return strcmp($bTitle, $aTitle);
                    }
                });
            } elseif ($sort == 'cost') {
                usort($models, function($a, $b) use ($order) {
                    $aCost = $a instanceof StandardRecipe ? $a->lastPrice : $a->cost;
                    $bCost = $b instanceof StandardRecipe ? $b->lastPrice : $b->cost;
                    
                    if ($order == 'asc') {
                        return $aCost <=> $bCost;
                    } else {
                        return $bCost <=> $aCost;
                    }
                });
            } elseif ($sort == 'costPercent') {
                usort($models, function($a, $b) use ($order) {
                    $aPercent = $a->getCostPercent();
                    $bPercent = $b->getCostPercent();
                    
                    if ($order == 'asc') {
                        return $aPercent <=> $bPercent;
                    } else {
                        return $bPercent <=> $aPercent;
                    }
                });
            }
        }
        
        // Paginación usando ActiveDataProvider
        $pagination = new Pagination([
            'page' => $page - 1,
            'pageSize' => $pageSize,
            'totalCount' => count($models)
        ]);
        
        // Obtener solo los modelos para la página actual
        $paginatedModels = array_slice($models, $pagination->offset, $pagination->limit);
        
        $dataProvider = new ActiveDataProvider([
            'models' => $paginatedModels,
            'pagination' => false // La paginación se maneja manualmente
        ]);
        
        return $this->render('menu', [
            'dataProvider' => $dataProvider,
            'availableRecipes' => $availableRecipes,
            'availableCombos' => $availableCombos,
            'pagination' => $pagination,
            'bundle' => $bundleModel,
            'business' => $business,
            'category' => $category,
            'sort' => $sort,          // Pasar el parámetro de ordenación a la vista
            'order' => $order         // Pasar el orden actual a la vista
        ]);
    }

    public function actionSelectUnselectForMenu($id, $type = 'recipe')
    {
        $business = RedisKeys::getBusinessData();
        /** @var $model StandardRecipe|Menu */
        if ($type == 'recipe') {
            $model = StandardRecipe::findOne(['business_id' => $business['id'], 'id' => $id]);
        } else {
            $model = Menu::findOne(['business_id' => $business['id'], 'id' => $id]);
        }

        Yii::$app->db->createCommand()
            ->update(
                $model::tableName(),
                ['in_menu' => !$model->in_menu],
                ['id' => $model->id]
            )->execute();

        return $this->redirect(['standard-recipe/menu-recipes']);
    }
    /*public function actionAnalytics($family = 'all', $sort = null, $direction = 'asc')
{
    $business = RedisKeys::getBusiness();

    // Obtener recetas y combos
    $recipes = StandardRecipe::find()
        ->where([
            'business_id' => $business->id,
            'in_menu' => true,
            'in_construction' => 0,
            'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN
        ]);
    
    $combos = Menu::find()
        ->innerJoin('recipe_category', 'recipe_category.id=menu.category_id')
        ->where([
            'menu.business_id' => $business->id,
            'in_menu' => true,
        ]);

    // Filtrar por familia si se especifica
    if ($family != 'all') {
        $recipes->andWhere(['type_of_recipe' => $family]);
        $combos->andWhere(['recipe_category.name' => $family]);
    }

    $recipes = $recipes->all();
    $combos = $combos->all();

    // Combinar recetas y combos
    $data = array_merge($recipes, $combos);
    
    // Aplicar ordenamiento según parámetros
    if ($sort) {
        usort($data, function ($a, $b) use ($sort, $direction) {
            $compare = 0;
            
            switch ($sort) {
                case 'name':
                    $compare = strcasecmp($a->name, $b->name);
                    break;
                    
                case 'cost-percent':
                    $compare = ($a->costPercent <=> $b->costPercent);
                    break;
                    
                case 'popularity':
                    $compare = ($a->sales <=> $b->sales);
                    break;
                    
                case 'sales':
                    $salesA = $a->price * $a->sales;
                    $salesB = $b->price * $b->sales;
                    $compare = ($salesA <=> $salesB);
                    break;
            }
            
            return ($direction === 'desc') ? -$compare : $compare;
        });
    }
    
    // Calcular posiciones para cada tipo de ordenamiento
    $sortByCostPercent = $data;
    usort($sortByCostPercent, function ($a, $b) {
        return ($a->costPercent <=> $b->costPercent);
    });
    
    $sortByPopularity = $data;
    usort($sortByPopularity, function ($a, $b) {
        return ($b->sales <=> $a->sales);
    });
    
    $sortBySales = $data;
    usort($sortBySales, function ($a, $b) {
        return (($b->price * $b->sales) <=> ($a->price * $a->sales));
    });
    
    // Convertir a strings para la vista
    $sortByCostPercent = ArrayHelper::getColumn($sortByCostPercent, function ($item) {
        return sprintf("%s_%s", get_class($item), $item->id);
    });
    
    $sortByPopularity = ArrayHelper::getColumn($sortByPopularity, function ($item) {
        return sprintf("%s_%s", get_class($item), $item->id);
    });
    
    $sortBySales = ArrayHelper::getColumn($sortBySales, function ($item) {
        return sprintf("%s_%s", get_class($item), $item->id);
    });

    // Calcular Pareto (como en tu versión original)
    $totalSales = array_sum(array_map(function($item) { 
        return $item->sales; 
    }, $data));
    
    $paretoItems = $data;
    usort($paretoItems, function ($a, $b) {
        return ($b->sales <=> $a->sales);
    });
    
    $accumulatedPercentage = 0;
    $paretoCategories = [];
    
    foreach ($paretoItems as $item) {
        $itemKey = sprintf("%s_%s", get_class($item), $item->id);
        
        if ($totalSales > 0) {
            $itemPercentage = ($item->sales / $totalSales) * 100;
            $accumulatedPercentage += $itemPercentage;
            
            if ($accumulatedPercentage <= 80) {
                $paretoCategories[$itemKey] = 'verde';
            } elseif ($accumulatedPercentage <= 95) {
                $paretoCategories[$itemKey] = 'amarillo';
            } else {
                $paretoCategories[$itemKey] = 'rojo';
            }
        } else {
            $paretoCategories[$itemKey] = 'gris';
        }
    }

    // Guardar datos en la sesión para usarlos en menu_improvement
    Yii::$app->session->set('menuAnalysisData', $data);
    Yii::$app->session->set('sortByCostPercent', $sortByCostPercent);
    Yii::$app->session->set('sortByPopularity', $sortByPopularity);
    Yii::$app->session->set('sortBySales', $sortBySales);
    Yii::$app->session->set('paretoCategories', $paretoCategories);
    return $this->render('analytics', [
        'data' => $data,
        'sortByCostPercent' => $sortByCostPercent,
        'sortByPopularity' => $sortByPopularity,
        'sortBySales' => $sortBySales,
        'family' => $family,
        'paretoCategories' => $paretoCategories,
        'totalSales' => $totalSales,
        'currentSort' => $sort,       // Para mostrar el orden actual
        'currentDirection' => $direction // Para mostrar la dirección
    ]);
}*/
public function actionAnalytics($family = 'all', $sort = null, $direction = 'asc', $year = null, $month = null)
{
    $business = RedisKeys::getBusiness();
    $currentYear = $year ?: date('Y');
    $selectedMonth = $month;
    
    // Obtener recetas con ActiveRecord para poder usar los métodos del modelo
    $recipesQuery = StandardRecipe::find()
        ->where([
            'business_id' => $business->id,
            'in_menu' => true,
            'in_construction' => 0,
            'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN
        ]);

    if ($family != 'all') {
        $recipesQuery->andWhere(['type_of_recipe' => $family]);
    }
    
    $recipes = $recipesQuery->all();

    // Obtener combos con ActiveRecord
    $combosQuery = Menu::find()
        ->innerJoin('recipe_category', 'recipe_category.id = menu.category_id')
        ->where([
            'menu.business_id' => $business->id,
            'in_menu' => true,
        ]);

    if ($family != 'all') {
        $combosQuery->andWhere(['recipe_category.name' => $family]);
    }
    
    $combos = $combosQuery->all();

    // Convertir a formato array para la vista, pero calculando correctamente
    $data = [];
    
    // Procesar recetas
    foreach ($recipes as $recipe) {
        // Obtener ventas desde monthly_sales
        $sales = 0;
        if ($selectedMonth !== null) {
            $sales = MonthlySales::getSales(MonthlySales::TYPE_RECIPE, $recipe->id, $selectedMonth, $currentYear);
        } else {
            $sales = MonthlySales::getSales(MonthlySales::TYPE_RECIPE, $recipe->id, null, $currentYear);
        }
        
        $data[] = [
            'id' => $recipe->id,
            'name' => $recipe->title,
            'type' => 'recipe',
            'type_of_recipe' => $recipe->type_of_recipe,
            'price' => $recipe->price,
            'cost' => $recipe->recipeLastPrice,  // Usar el método del modelo que calcula correctamente
            'sales' => $sales,
            'cost_percent' => $recipe->getCostPercent(),  // Usar el método del modelo
            'sales_value' => $recipe->price * $sales
        ];
    }
    
    // Procesar combos
    foreach ($combos as $combo) {
        // Obtener ventas desde monthly_sales
        $sales = 0;
        if ($selectedMonth !== null) {
            $sales = MonthlySales::getSales(MonthlySales::TYPE_MENU, $combo->id, $selectedMonth, $currentYear);
        } else {
            $sales = MonthlySales::getSales(MonthlySales::TYPE_MENU, $combo->id, null, $currentYear);
        }
        
        $data[] = [
            'id' => $combo->id,
            'name' => $combo->name,
            'type' => 'combo',
            'type_of_recipe' => $combo->category->name ?? '',
            'price' => $combo->total_price,
            'cost' => $combo->total_cost,
            'sales' => $sales,
            'cost_percent' => $combo->getCostPercent(),  // Usar el método del modelo
            'sales_value' => $combo->total_price * $sales
        ];
    }

    // Calcular total de ventas para Pareto
    $totalSales = array_sum(array_column($data, 'sales'));

    // Función para ordenar según parámetros
    $sortFunction = function ($a, $b) use ($sort, $direction) {
        $compare = 0;
        
        switch ($sort) {
            case 'name':
                $compare = strcasecmp($a['name'], $b['name']);
                break;
                
            case 'cost-percent':
                $compare = ($a['cost_percent'] <=> $b['cost_percent']);
                break;
                
            case 'popularity':
                $compare = ($a['sales'] <=> $b['sales']);
                break;
                
            case 'sales':
                $compare = ($a['sales_value'] <=> $b['sales_value']);
                break;
        }
        
        return ($direction === 'desc') ? -$compare : $compare;
    };

    // Ordenar datos principales si es necesario
    if ($sort) {
        usort($data, $sortFunction);
    }

    // Generar versiones ordenadas una sola vez
    $sortedVersions = [
        'costPercent' => $data,
        'popularity' => $data,
        'sales' => $data
    ];

    usort($sortedVersions['costPercent'], fn($a, $b) => $b['cost_percent'] <=> $a['cost_percent']);
    usort($sortedVersions['popularity'], fn($a, $b) => $a['sales'] <=> $b['sales']);
    usort($sortedVersions['sales'], fn($a, $b) => $a['sales_value'] <=> $b['sales_value']);

    // Generar claves únicas y categorías Pareto
    $paretoItems = $sortedVersions['popularity']; // Ya está ordenado por popularidad
    $paretoCategories = [];
    $accumulatedPercentage = 0;

    foreach ($paretoItems as $item) {
        $itemKey = "{$item['type']}_{$item['id']}";
        
        if ($totalSales > 0) {
            $itemPercentage = ($item['sales'] / $totalSales) * 100;
            $accumulatedPercentage += $itemPercentage;
            
            if ($accumulatedPercentage <= 80) {
                $paretoCategories[$itemKey] = 'verde';
            } elseif ($accumulatedPercentage <= 95) {
                $paretoCategories[$itemKey] = 'amarillo';
            } else {
                $paretoCategories[$itemKey] = 'rojo';
            }
        } else {
            $paretoCategories[$itemKey] = 'gris';
        }
    }

    // Preparar datos para la vista
    $prepareForView = function($items) {
        return array_map(fn($item) => "{$item['type']}_{$item['id']}", $items);
    };

    // Guardar en sesión
    Yii::$app->session->set('menuAnalysisData', $data);
    Yii::$app->session->set('sortByCostPercent', $prepareForView($sortedVersions['costPercent']));
    Yii::$app->session->set('sortByPopularity', $prepareForView($sortedVersions['popularity']));
    Yii::$app->session->set('sortBySales', $prepareForView($sortedVersions['sales']));
    Yii::$app->session->set('paretoCategories', $paretoCategories);

    return $this->render('analytics', [
        'data' => $data,
        'sortByCostPercent' => $prepareForView($sortedVersions['costPercent']),
        'sortByPopularity' => $prepareForView($sortedVersions['popularity']),
        'sortBySales' => $prepareForView($sortedVersions['sales']),
        'family' => $family,
        'paretoCategories' => $paretoCategories,
        'totalSales' => $totalSales,
        'currentSort' => $sort,
        'currentDirection' => $direction,
        'selectedYear' => $currentYear,
        'selectedMonth' => $selectedMonth
    ]);
}
    public function actionMenuImprovement()
    {
        $business = RedisKeys::getBusiness();

        // Obtener recetas con datos de ventas
        $recipes = StandardRecipe::find()
            ->where([
                'business_id' => $business->id,
                'in_menu' => true,
                'in_construction' => 0,
                'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN
            ])->all();

        $combos = Menu::find()
            ->innerJoin('recipe_category', 'recipe_category.id=menu.category_id')
            ->where([
                'menu.business_id' => $business->id,
                'in_menu' => true,
            ])->all();

        // Crear array de datos con ventas totales incluidas
        $data = [];

        // Procesar recetas
        foreach ($recipes as $recipe) {
            $sales = MonthlySales::getSales(MonthlySales::TYPE_RECIPE, $recipe->id, null, null);
            $data[] = [
                'model' => $recipe,
                'monthly_sales' => $sales,
                'sales_value' => $recipe->price * $sales
            ];
        }

        // Procesar combos
        foreach ($combos as $combo) {
            $sales = MonthlySales::getSales(MonthlySales::TYPE_MENU, $combo->id, null, null);
            $data[] = [
                'model' => $combo,
                'monthly_sales' => $sales,
                'sales_value' => $combo->total_price * $sales
            ];
        }

        return $this->render('menu_improvement', [
            'data' => $data,
            'formatter' => $business->getFormatter()
        ]);
    }

    public function actionProfitComparison($month = null, $year = null)
    {
        // Si no se proporciona mes/año, usar el mes anterior por defecto
        if ($month === null) {
            $month = (int)date('m') - 1;
            if ($month < 1) {
                $month = 12;
                $year = (int)date('Y') - 1;
            } else {
                $year = (int)date('Y');
            }
        } else {
            $month = (int)$month;
        }
        if ($year === null) {
            $year = (int)date('Y');
        } else {
            $year = (int)$year;
        }

        $business = RedisKeys::getBusiness();

        $recipes = StandardRecipe::find()
            ->where([
                'business_id' => $business->id,
                'in_menu' => true,
                'in_construction' => 0,
                'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN
            ])->all();
        $combos = Menu::find()
            ->innerJoin('recipe_category', 'recipe_category.id=menu.category_id')
            ->where([
                'menu.business_id' => $business->id,
                'in_menu' => true,
            ])->all();

        $data = array_merge($recipes, $combos);
        $total = count($data);
        $theoreticalCost = 0;
        $desiredCost = 0;
        $totalSales = 0;
        array_walk($data, function ($item) use (&$theoreticalCost, &$totalSales, &$desiredCost, $month, $year) {
            $theoreticalCost += $item->getCostPercent(false);
            $desiredCost += $item->getCostPercent(true);

            // Calcular ventas usando datos históricos completos del mes seleccionado
            if ($item instanceof StandardRecipe) {
                $sales = MonthlySales::getSales(MonthlySales::TYPE_RECIPE, $item->id, $month, $year);
            } else {
                $sales = MonthlySales::getSales(MonthlySales::TYPE_MENU, $item->id, $month, $year);
            }
            $totalSales += ($item->price * $sales);
        });

        if ($total != 0) {
            $theoreticalCost = $theoreticalCost / $total;
            $desiredCost = $desiredCost / $total;
        } else {
            $theoreticalCost = 0;
            $desiredCost = 0;
        }

        return $this->render('profit_comparison', [
            'theoreticalCost' => $theoreticalCost,
            'desiredCost' => $desiredCost,
            'totalSales' => $totalSales,
            'business' => $business,
            'selectedMonth' => $month,
            'selectedYear' => $year
        ]);
    }    public function actionMatrixBcg($type = 'all', $year = null)
    {
        // Si no se proporciona año, usar el actual
        if ($year === null) {
            $year = (int)date('Y');
        }

        $business = RedisKeys::getBusiness();

        return $this->render('matrix', $business->getBcgData($type, $year));
    }

    public function actionCharts($month = null, $year = null)
    {
        // Si no se proporciona mes/año, usar el mes actual por defecto
        if ($month === null) {
            $month = (int)date('m');
        } else {
            $month = (int)$month;
        }
        if ($year === null) {
            $year = (int)date('Y');
        } else {
            $year = (int)$year;
        }

        $business = RedisKeys::getBusiness();
        $categories = RecipeCategory::find()
            ->where([
                'business_id' => $business['id']
            ])->all();

        $totalSales = array_sum(array_map(function ($category) use ($month, $year) {
            return $category->getTotalSales($month, $year);
        }, $categories));


        return $this->render('charts', [
            'totalSales' => $totalSales,
            'categories' => $categories,
            'selectedMonth' => $month,
            'selectedYear' => $year
        ]);
    }

    public function actionDuplicateRecipes()
    {
        $post = Yii::$app->request->post();

        $recipes = StandardRecipe::find()->where(['id' => $post['recipes']])->all();

        foreach ($recipes as $recipe) {
            $recipe->duplicate();
        }

        return $this->redirect(Url::previous('index-recipe'));
    }

    /**
     * Finds the StandardRecipe model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return StandardRecipe the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = StandardRecipe::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
    public function actionCheckTitle()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $title = Yii::$app->request->post('title');
        $businessId = Yii::$app->request->post('business_id');
        $type = Yii::$app->request->post('type');
        $exists = StandardRecipe::find()
            ->where(['title' => $title, 'business_id' => $businessId, 'type' => $type])
            ->exists();

        return ['exists' => $exists];
    }
    public function actionDownloadRecipes($type)
    {
        $id = Yii::$app->request->get();
        $business = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        if (count($id) > 1) {
            $recipe_id = explode(',' ,$id['id']);
            $recipes = StandardRecipe::find()
                ->where([
                    'business_id' => $business['id'],
                    'in_construction' => 0,
                    'type' => $type,
                    'id' => $recipe_id
                ])
                ->with('category')
                ->all();
        } else {
        $recipes = StandardRecipe::find()
            ->where([
                'business_id' => $business['id'],
                'in_construction' => 0,
                'type' => $type
            ])
            ->with('category')
            ->all();
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($type == StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
            $sheet->setCellValue('A1', 'A / B');
            $sheet->setCellValue('B1', 'Nombre');
            $sheet->setCellValue('C1', 'Familia');
            $sheet->setCellValue('D1', 'Costo');
            $sheet->setCellValue('E1', 'Precio de venta');
            $sheet->setCellValue('F1', 'Porcentaje de costo');
            $sheet->setCellValue('G1', 'Cantidad de ingredientes');
            $sheet->setCellValue('H1', 'Cantidad de Sub-recetas');
            
            // Configurar ancho de columnas para recetas principales
            $sheet->getColumnDimension('A')->setWidth(20); // Tipo (Alimento/Bebida)
            $sheet->getColumnDimension('B')->setWidth(25); // Nombre
            $sheet->getColumnDimension('C')->setWidth(20); // Categoría
            $sheet->getColumnDimension('D')->setWidth(15); // Costo
            $sheet->getColumnDimension('E')->setWidth(18); // Precio de venta
            $sheet->getColumnDimension('F')->setWidth(22); // Porcentaje de costo
            $sheet->getColumnDimension('G')->setWidth(25); // Cantidad de ingredientes
            $sheet->getColumnDimension('H')->setWidth(25); // Cantidad de Sub-recetas
        } else {
            $sheet->setCellValue('A1', 'Nombre');
            $sheet->setCellValue('B1', 'Categoría');
            $sheet->setCellValue('C1', 'Costo');
            $sheet->setCellValue('D1', 'Cantidad de ingredientes');
            $sheet->setCellValue('E1', 'Cantidad de Recetas');
            $sheet->setCellValue('F1', 'Cantidad de SubRecetas');
            
            // Configurar ancho de columnas para subrecetas
            $sheet->getColumnDimension('A')->setWidth(25); // Nombre
            $sheet->getColumnDimension('B')->setWidth(20); // Categoría
            $sheet->getColumnDimension('C')->setWidth(15); // Costo
            $sheet->getColumnDimension('D')->setWidth(25); // Cantidad de ingredientes
            $sheet->getColumnDimension('E')->setWidth(20); // Cantidad de Recetas
            $sheet->getColumnDimension('F')->setWidth(25); // Cantidad de Recetas
        }

        // Configurar estilos para los headers
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => '000000']
            ],
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
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];

        // Aplicar estilo a los headers
        if ($type == StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
            $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
        } else {
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
        }

        // Configurar estilo para centrar todas las celdas de datos
        $dataStyle = [
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
        ];

        $row = 2;
        foreach ($recipes as $recipe) {
            if ($type == StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
                $sheet->setCellValue('A' . $row, ($recipe->category->is_food === null) ? 'Sin definir' : ($recipe->category->is_food ? 'Alimento' : 'Bebida'));
                $sheet->setCellValue('B' . $row, $recipe->title);
                $sheet->setCellValue('C' . $row, $recipe->category ? $recipe->category->name : '');
                $sheet->setCellValue('D' . $row, '$' . number_format($recipe->recipeLastPrice, 2));
                $sheet->setCellValue('E' . $row, '$' . $recipe->price);
                $sheet->setCellValue('F' . $row, $recipe->costPercent*100 . '%');
                $sheet->setCellValue('G' . $row, $recipe->getIngredientRelations()->count());
                $sheet->setCellValue('H' . $row, $recipe->getSubStandardRecipes()->count());
            } else {
                $sheet->setCellValue('A' . $row, $recipe->title);
                $sheet->setCellValue('B' . $row, $recipe->category ? $recipe->category->name : '');
                $sheet->setCellValue('C' . $row, '$' . number_format($recipe->recipeLastPrice, 2));
                $sheet->setCellValue('D' . $row, $recipe->getIngredientRelations()->count());
                //die(var_dump($recipe->getSubRecipeCount()['sub']));
                $sheet->setCellValue('E' . $row, $recipe->getSubRecipeCount()['main']);
                $sheet->setCellValue('F' . $row, $recipe->getSubRecipeCount()['sub']);
            }
            $row++;
        }

        // Aplicar estilo de centrado a todas las celdas de datos
        $lastRow = $row - 1;
        if ($type == StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
            $sheet->getStyle('A2:H' . $lastRow)->applyFromArray($dataStyle);
            // Alineación especial para la columna de nombres (izquierda)
            $sheet->getStyle('B2:B' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        } else {
            $sheet->getStyle('A2:F' . $lastRow)->applyFromArray($dataStyle);
            // Alineación especial para la columna de nombres (izquierda)
            $sheet->getStyle('A2:A' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        }

        // Configurar altura de filas para mejor visualización
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->getRowDimension('1')->setRowHeight(25); // Header más alto

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = $type == \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_MAIN ? 'Recetas.xlsx':'Sub-Recetas.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Yii::$app->response->sendFile($tempFile, $fileName);
    }

    public function actionDownloadRecipesPdf($type)
    {
        $id = Yii::$app->request->get();
        $business = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        if (count($id) > 1) {
            $recipe_id = explode(',' ,$id['id']);
            $recipes = StandardRecipe::find()
                ->where([
                    'business_id' => $business['id'],
                    'in_construction' => 0,
                    'type' => $type,
                    'id' => $recipe_id
                ])
                ->all();
        } else {
        $recipes = StandardRecipe::find()
            ->where([
                'business_id' => $business['id'],
                'in_construction' => 0,
                'type' => $type
            ])
            ->all();
        }
        // die(var_dump($recipes));
        $html = '<h1>' . ($type == StandardRecipe::STANDARD_RECIPE_TYPE_MAIN ? 'Recetas' : 'Sub Recetas') . '</h1>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0">';
        $html .= '<tr><th>Nombre</th><th>Costo</th>';
        if ($type == StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
            $html .= '<th>Precio de venta</th><th>Porcentaje de costo</th>';
            $html .= '<th>Cantidad de ingredientes</th><th>Cantidad de Sub-recetas</th>';
        } else {
            $html .= '<th>Cantidad de ingredientes</th><th>Cantidad de Recetas</th><th>Cantidad de Sub-recetas</th>';
        }
        $html .= '</tr>';

        foreach ($recipes as $recipe) {
            $html .= '<tr>';
            $html .= '<td>' . $recipe->title . '</td>';
            $html .= '<td style="text-align: center;">$' . number_format($recipe->recipeLastPrice, 2) . '</td>';
            if ($type == StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
            $html .= '<td style="text-align: center;">$' . $recipe->price . '</td>';
            $html .= '<td style="text-align: center;">' . ($recipe->costPercent)*100 . '%</td>';
            }
            $html .= '<td style="text-align: center;">' . $recipe->getIngredientRelations()->count() . '</td>';
            if ($type == StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
                $html .= '<td style="text-align: center;">' . $recipe->getSubStandardRecipes()->count() . '</td>';
            } else {
                // Para subrecetas: mostrar cantidad de recetas y cantidad de subrecetas
                $html .= '<td style="text-align: center;">' . $recipe->getSubRecipeCount()['main'] . '</td>';
                $html .= '<td style="text-align: center;">' . $recipe->getSubRecipeCount()['sub'] . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>';
       // die(var_dump($post));

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => Yii::getAlias('@runtime/mpdf')
        ]);
        $mpdf->WriteHTML($html);
        $fileName = $type == \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_MAIN ? 'Recetas.pdf':'Sub-Recetas.pdf';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $mpdf->Output($tempFile, \Mpdf\Output\Destination::FILE);

        return Yii::$app->response->sendFile($tempFile, $fileName);
    }
    public function actionDownloadCompleteRecipePdf()
    {
        // Aumentar el límite de PCRE para manejar HTML con imágenes grandes
        ini_set('pcre.backtrack_limit', '10000000'); // 10 millones
        ini_set('memory_limit', '512M'); // También aumentar memoria si es necesario
        
        $get = Yii::$app->request->get();
        $business = \backend\helpers\RedisKeys::getBusiness();
        
        // Verificar si se quiere descargar todas las recetas
        if (isset($get['all']) && $get['all'] === 'true') {
            // Buscar todas las recetas del negocio
            $recipes = StandardRecipe::find()
                ->where([
                    'business_id' => $business->id,
                    'in_construction' => 0,
                    'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN
                ])
                ->all();
        } else {
            // Buscar las recetas seleccionadas por ID
            $selectedRecipes = isset($get['id']) ? explode(',', $get['id']) : [];
            $recipes = StandardRecipe::find()->where(['id' => $selectedRecipes])->all();
        }
        
        if (empty($recipes)) {
            throw new \yii\web\NotFoundHttpException('No se encontraron recetas.');
        }
        // Generar PDF
        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => Yii::getAlias('@runtime/mpdf'),
            'default_font' => 'dejavusans', // Usar una fuente compatible con UTF-8
        ]);
        
        // Añadir CSS personalizado para asegurar que el diseño se mantenga
        $stylesheet = '
            body {
                font-size: 10pt; /* Letra más pequeña para el contenido */
                line-height: 1.3;
                font-family: dejavusans, sans-serif;
                color: #333;
            }
            .portada {
                height: 100%;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                text-align: center;
                padding: 40px;
                background-color: #f9f9f9;
            }
            .portada h1 {
                font-size: 48pt;
                margin-top: 80px;
                font-weight: bold;
                color: #333;
                text-transform: uppercase;
                letter-spacing: 2px;
                text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            }
            .portada h2 {
                font-size: 30pt;
                margin-top: 50px;
                color: #444;
                font-style: italic;
            }
            .portada .fecha {
                font-size: 18pt;
                margin-top: 60px;
                color: #555;
                font-weight: 300;
            }
            .portada .logo {
                text-align: right;
                margin-top: 80px;
            }
            .indice {
                padding: 20px;
            }
            .indice h2 {
                font-size: 24pt;
                margin-bottom: 30px;
                color: #333;
                text-align: center;
                border-bottom: 1px solid #ccc;
                padding-bottom: 10px;
                text-transform: uppercase;
            }
            .indice ul {
                list-style-type: disc;
                margin-left: 30px;
            }
            .indice li {
                font-size: 13pt;
                margin-bottom: 10px;
                color: #333;
            }
            h1 {
                font-size: 16pt;
                margin-top: 20px;
                margin-bottom: 10px;
                color: #222;
                border-bottom: 1px solid #ddd;
                padding-bottom: 5px;
            }
            h2 {
                font-size: 14pt;
                color: #333;
                margin-top: 15px;
                margin-bottom: 8px;
            }
            h3 {
                font-size: 12pt;
                color: #444;
                margin-top: 10px;
                margin-bottom: 5px;
            }
            table {
                font-size: 10pt;
                border-collapse: collapse;
                width: 100%;
            }
            table th {
                background-color: #f2f2f2;
                font-weight: bold;
                text-align: center;
                padding: 6px;
            }
            table td {
                padding: 5px;
                border: 1px solid #ddd;
            }
            hr {
                margin: 15px 0;
                border: 0;
                border-top: 1px solid #eee;
            }
        ';
        $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
        
        // 1. PORTADA
        $business = RedisKeys::getBusiness();
        $html = '';
        $html .= '<div class="portada">';
        $html .= '<div>';
        $html .= '<h1>RECETARIO</h1>';
        $html .= '<h2>' . htmlspecialchars($business->name) . '</h2>';
        $html .= '<div class="fecha">' . date('d/m/Y') . '</div>';
        $html .= '</div>';
        
        // Logo de Coach Restaurantero en esquina inferior derecha
        $html .= '<div class="logo">';
        
        // Ruta al logo en el sistema de archivos
        $logoPath = Yii::getAlias('@backend/web/images/logo1.png');
        $logoFound = false;
        
        // Intentar cargar la imagen como base64 si existe en el sistema de archivos
        if (file_exists($logoPath)) {
            $imageData = base64_encode(file_get_contents($logoPath));
            $imageExtension = pathinfo($logoPath, PATHINFO_EXTENSION);
            $imageSrc = "data:image/$imageExtension;base64,{$imageData}";
            $html .= '<div style="display: inline-block; background: white; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">';
            $html .= '<img src="' . $imageSrc . '" style="max-width: 200px; height: auto;" />';
            $html .= '</div>';
            $logoFound = true;
        }
        
        // Si no se encontró ninguna imagen, mostrar el texto
        if (!$logoFound) {
            $html .= '<div style="display: inline-block; background: white; border-radius: 20px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: 1px solid #e0e0e0;">';
            $html .= '<p style="font-size: 18pt; font-weight: bold; margin: 0;">Coach Restaurantero</p>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        $mpdf->WriteHTML($html);
        $mpdf->AddPage();
        
        // 2. ÍNDICE
        $html = '<div class="indice">';
        $html .= '<h2>ÍNDICE</h2>';
        $html .= '<ul>';
        foreach ($recipes as $index => $recipe) {
            $html .= '<li>' . htmlspecialchars($recipe->title) . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        
        $mpdf->WriteHTML($html);
        $mpdf->AddPage();
        
        // 3. CONTENIDO DE RECETAS
        // Build category is_food map
        $_catIsFoodMapPdf = [];
        foreach (RecipeCategory::find()->where(['business_id' => $business->id, 'type' => RecipeCategory::TYPE_MAIN])->all() as $_c) {
            $_catIsFoodMapPdf[$_c->name] = $_c->is_food;
        }

        $html = '';
        $isFirst = true;
        
        foreach ($recipes as $recipe) {
            // Añadir salto de página antes de cada receta (excepto la primera)
            if (!$isFirst) {
                $mpdf->AddPage();
            } else {
                $isFirst = false;
            }
            
            // Título de la receta
            $html .= '<h1>' . htmlspecialchars($recipe->title) . '</h1>';
    
            // Contenedor de tabla para la imagen y los datos de la receta
            $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">';
            $html .= '<tr>';
    
            // Columna izquierda: Datos de la receta (50% del ancho)
            $html .= '<td width="60%" style="vertical-align: top; padding-right: 10px;">';
            $html .= '<hr style="margin: 20px 0; border: 1px solid #ccc;">';
            $html .= '<h3><strong>Tipo de receta:</strong> ' . htmlspecialchars($recipe->type_of_recipe ?? '') . '</h3>';
            $html .= '<hr style="margin: 20px 0; border: 1px solid #ccc;">';
            if ($recipe->type == \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
                $_isFoodVal = $_catIsFoodMapPdf[$recipe->type_of_recipe] ?? null;
                $_isFoodLabel = ($_isFoodVal === null) ? 'Sin definir' : ($_isFoodVal ? 'Alimento' : 'Bebida');
                $html .= '<h3><strong>Alimento o Bebida:</strong> ' . $_isFoodLabel . '</h3>';
                $html .= '<hr style="margin: 20px 0; border: 1px solid #ccc;">';
            }
            $html .= '<h3 style="margin-bottom: 15px"><strong>Tiempo de preparación:</strong> ' . htmlspecialchars($recipe->time_of_preparation ?? '') . '</h3>';
            $html .= '<hr style="margin: 20px 0; border: 1px solid #ccc;">';
            $html .= '<h3 style="margin-bottom: 15px"><strong>Rendimiento:</strong> ' . htmlspecialchars($recipe->yield ?? '') . ' ' . htmlspecialchars($recipe->yield_um ?? '') . '</h3>';
            $html .= '<hr style="margin: 20px 0; border: 1px solid #ccc;">';
            $html .= '<h3 style="margin-bottom: 15px"><strong>Porciones:</strong> ' . htmlspecialchars($recipe->portions ?? '') . '</h3>';
            $html .= '<hr style="margin: 20px 0; border: 1px solid #ccc;">';
            $html .= '<h3 style="margin-bottom: 15px"><strong>Duración:</strong> ' . htmlspecialchars($recipe->lifetime ?? '') . '</h3>';
    
            // Si es receta principal, mostrar precios y costos
            if ($recipe->type == \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
                $html .= '<hr style="margin: 20px 0; border: 1px solid #ccc;">';
                $html .= '<h3><strong>Precio:</strong> $' . htmlspecialchars($recipe->price ?? '') . '</h3>';
                $html .= '<hr style="margin: 20px 0; border: 1px solid #ccc;">';
                $html .= '<h3><strong>Costo:</strong> $' . number_format((float)($recipe->lastPrice ?? 0), 2) . '</h3>';
                $html .= '<hr style="margin: 20px 0; border: 1px solid #ccc;">';
                $html .= '<h3><strong>% de Costo :</strong> ' . number_format((float)($recipe->costPercent*100 ?? 0), 2) . '</h3>';
                $html .= '<hr style="margin: 20px 0; border: 1px solid #ccc;">';
            }
            $html .= '</td>'; // Cierre de la columna izquierda
    
            // Columna derecha: Imagen principal (50% del ancho)
            $html .= '<td width="40%" style="vertical-align: top; padding-left: 10px; height: 550px;">';
            $images = $recipe->getImages();
            $mainImageFound = false;
            if (!empty($images)) {
                foreach ($images as $image) {
                    if ($image['isMain'] == 1 && $image['filePath'] !== 'placeholder.svg') {
                        $imagePath = Yii::getAlias('@web') . $image->getPath(); // Ruta relativa
                        $imagePath1 = '/app/backend/web/' . $imagePath; // Ruta absoluta
    
                        if (file_exists($imagePath1)) {
                            // Obtener la extensión real de la imagen
                            $imageExtension = pathinfo($imagePath1, PATHINFO_EXTENSION);
                            $imageData = base64_encode(file_get_contents($imagePath1));
                            $imageSrc = "data:image/$imageExtension;base64,{$imageData}";
    
                            // Agregar la imagen principal (a la derecha)
                            $html .= '<div style="height: 100%; display: flex; align-items: center; justify-content: center;">';
                            $html .= '<div style="background: white; border-radius: 12px; padding: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; display: inline-block;">';
                            $html .= '<img src="' . $imageSrc . '" style="max-width: 100%; max-height: 530px; height: auto; border-radius: 8px;" />';
                            $html .= '</div>';
                            $html .= '</div>';
                            $mainImageFound = true;
                            break; // Solo necesitamos la imagen principal
                        }
                    }
                }
            }
            if (!$mainImageFound) {
                // Agregar un contenedor vacío para la imagen principal
                $html .= '<div style="background: white; border-radius: 12px; padding: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; display: inline-block; width: 90%;">';
                $html .= '<div style="width: 100%; height: 200px; border: 1px dashed #ccc; border-radius: 8px; display: flex; justify-content: center; align-items: center;">';
                $html .= '<p style="color: #999; font-style: italic;">Sin imagen</p>';
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</td>'; // Cierre de la columna derecha
    
            $html .= '</tr>';
            $html .= '</table>'; // Cierre de la tabla
            //die(var_dump($html));
            $mpdf->WriteHTML($html);
            
            // PARTE 2: Solo ingredientes
            $html = '';
            $html .= '<h2>Ingredientes</h2>';
            $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
            $html .= '<tr><th>INGREDIENTE</th><th>CANTIDAD</th><th>COSTO</th></tr>';
            foreach ($recipe->ingredientRelations as $index => $ingredientStandardRecipe) {
                $cost = $ingredientStandardRecipe->lastUnitPrice * $ingredientStandardRecipe->quantity;
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($ingredientStandardRecipe->ingredient->ingredient) . '</td>';
                $html .= '<td>' . htmlspecialchars($ingredientStandardRecipe->quantity . ' ' . $ingredientStandardRecipe->ingredient->portion_um) . '</td>';
                $html .= '<td>' . htmlspecialchars($business->formatter->asCurrency($cost)) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $mpdf->WriteHTML($html);
    
            // PARTE 3: Solo procedimiento
            $mpdf->AddPage(); // Nueva página para el procedimiento
            $html = '';
            // Separar los pasos en procedimientos y cuidados especiales
            $steps = RecipeStep::find()->where(['recipe_id' => $recipe->id])->all();
            $procedureSteps = [];
            $specialSteps = [];
            foreach ($steps as $step) {
                if ($step->type === 'procedure') {
                    $procedureSteps[] = $step;
                } elseif ($step->type === 'special') {
                    $specialSteps[] = $step;
                }
            }
    
            // Procedimiento
            $html .= '<h2>Procedimiento</h2>';
            $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
            $html .= '<tr><th>#</th><th>ACTIVIDAD</th><th>TIEMPO</th><th>INDICADOR</th></tr>';
            foreach ($procedureSteps as $step) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($step->number) . '</td>';
                $html .= '<td>' . htmlspecialchars($step->activity) . '</td>';
                $html .= '<td>' . htmlspecialchars($step->time ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($step->indicator ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $mpdf->WriteHTML($html);
            
            // PARTE 3: Imágenes, cuidados especiales, alérgenos y equipo
            $html = '';
            $html .= '<h3>Fotos de los pasos del procedimiento</h3>';
            $procedureSteps = $recipe->getRecipeSteps()->andWhere(['type' => \common\models\RecipeStep::STEP_TYPE_PROCEDURE])->all();
            if (!empty($procedureSteps)) {
                foreach ($procedureSteps as $step) {
                    $image = $step->getImage();
                    $imgUrl = $image ? $image->getUrl() : null;
                    $imgThumb = $image ? $image->getUrl('200x200') : null;
                    // Evitar procesar imágenes placeholder.svg
                    $isRealImage = $imgUrl && strpos($imgUrl, 'no-image') === false && strpos($imgThumb, 'no-image') === false && strpos($image->filePath, 'placeholder.svg') === false;
                    $html .= '<div style="margin-bottom: 18px; text-align: center;">';
                    $html .= '<div style="font-weight:bold; margin-bottom:4px;">Paso ' . htmlspecialchars($step->number) . ': ' . htmlspecialchars($step->activity) . '</div>';
                    if ($isRealImage && $image) {
                        $webroot = Yii::getAlias('@webroot');
                        $relativePath = $image->getPath('400x400');
                        $absolutePath = $webroot . '/' . ltrim($relativePath, '/');
                        // Solo intentar mostrar la imagen si el archivo existe y no es placeholder.svg
                        if ($relativePath && file_exists($absolutePath) && strpos($relativePath, 'placeholder.svg') === false) {
                            $imageExtension = pathinfo($absolutePath, PATHINFO_EXTENSION);
                            $imageData = base64_encode(file_get_contents($absolutePath));
                            $imageSrc = "data:image/$imageExtension;base64,{$imageData}";
                            $html .= '<img src="' . $imageSrc . '" style="max-width:220px; max-height:220px; border-radius:8px; border:1px solid #ccc; margin-bottom:4px;" />';
                        } else {
                            $html .= '<span style="color:#999; font-style:italic;">Sin imagen</span>';
                        }
                    } else {
                        $html .= '<span style="color:#999; font-style:italic;">Sin imagen</span>';
                    }
                    $html .= '</div>';
                }
            } else {
                $html .= '<div style="color:#999; font-style:italic;">No hay pasos de procedimiento.</div>';
            }
            $mpdf->WriteHTML($html);
            

            // PARTE 5: Cuidados y medidas especiales (tabla)
            $html = '';
            $html .= '<h2>Cuidados y medidas especiales</h2>';
            $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
            $html .= '<tr><th>#</th><th>ACTIVIDAD</th><th>TIEMPO</th><th>INDICADOR</th></tr>';
            foreach ($specialSteps as $step) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($step->number) . '</td>';
                $html .= '<td>' . htmlspecialchars($step->activity) . '</td>';
                $html .= '<td>' . htmlspecialchars($step->time ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($step->indicator ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $mpdf->WriteHTML($html);

            // PARTE 5b: Fotos de cuidados y medidas especiales
            $html = '';
            $html .= '<h3>Fotos de los pasos de cuidados y medidas especiales</h3>';
            if (!empty($specialSteps)) {
                foreach ($specialSteps as $step) {
                    $image = $step->getImage();
                    $imgUrl = $image ? $image->getUrl() : null;
                    $imgThumb = $image ? $image->getUrl('200x200') : null;
                    $isRealImage = $imgUrl && strpos($imgUrl, 'no-image') === false && strpos($imgThumb, 'no-image') === false && strpos($image->filePath, 'placeholder.svg') === false;
                    $html .= '<div style="margin-bottom: 18px; text-align: center;">';
                    $html .= '<div style="font-weight:bold; margin-bottom:4px;">Paso ' . htmlspecialchars($step->number) . ': ' . htmlspecialchars($step->activity) . '</div>';
                    if ($isRealImage && $image) {
                        $webroot = Yii::getAlias('@webroot');
                        $relativePath = $image->getPath('400x400');
                        $absolutePath = $webroot . '/' . ltrim($relativePath, '/');
                        if (file_exists($absolutePath)) {
                            $imageExtension = pathinfo($absolutePath, PATHINFO_EXTENSION);
                            $imageData = base64_encode(file_get_contents($absolutePath));
                            $imageSrc = "data:image/$imageExtension;base64,{$imageData}";
                            $html .= '<img src="' . $imageSrc . '" style="max-width:220px; max-height:220px; border-radius:8px; border:1px solid #ccc; margin-bottom:4px;" />';
                        } else {
                            $html .= '<span style="color:#999; font-style:italic;">Sin imagen</span>';
                        }
                    } else {
                        $html .= '<span style="color:#999; font-style:italic;">Sin imagen</span>';
                    }
                    $html .= '</div>';
                }
            } else {
                $html .= '<div style="color:#999; font-style:italic;">No hay pasos de cuidados especiales.</div>';
            }
            $mpdf->WriteHTML($html);
    
            // PARTE 6: Solo alérgenos
            $html = '';
            $allergies = Yii::$app->params['allergies'];
            $selectedAllergies = [];
            $html .= '<h2>Alérgenos</h2>';
    
            if (!empty($recipe->allergies)) {
                $selectedAllergies = array_values(explode(";", trim($recipe->allergies)));
                $allergies = array_unique(array_values(array_merge($allergies, $selectedAllergies)));
            }
            $html .= '<table style="width: 100%; border-collapse: collapse;">';
            $html .= '<tr>'; // Fila de la tabla
    
            foreach ($allergies as $index => $allergy) {
                // Verificar si el alérgeno está seleccionado
                $isChecked = in_array($allergy, $selectedAllergies);
    
                // Usar un símbolo de checkbox marcado o desmarcado
                $checkbox = $isChecked ? '☑' : '☐'; // ☑ = Marcado, ☐ = Desmarcado
    
                // Mostrar el alérgeno con el checkbox en una celda de la tabla
                $html .= '<td style="padding: 5px; border: 1px solid #ccc;">' . $checkbox . ' ' . htmlspecialchars($allergy) . '</td>';
    
                // Si hay 4 alérgenos en una fila, cerrar la fila y abrir una nueva
                if (($index + 1) % 4 === 0) {
                    $html .= '</tr><tr>'; // Cerrar fila y abrir una nueva
                }
            }
            if (count($allergies) % 5 !== 0) {
                $html .= '</tr>';
            }
    
            $html .= '</table>'; // Cierre de la tabla
            $mpdf->WriteHTML($html);
            
            // PARTE 7: Solo equipo
            $html = '';
            $html .= '<h2>Equipo y Utensilios</h2>';
            
            // Procesar el equipo desde JSON
            if (!empty($recipe->equipment)) {
                try {
                    $equipmentData = json_decode($recipe->equipment, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && is_array($equipmentData)) {
                        $html .= '<table border="1" cellpadding="8" cellspacing="0" width="100%">';
                        $html .= '<tr style="background-color: #f2f2f2;">';
                        $html .= '<th style="text-align: left; font-weight: bold;">Sección</th>';
                        $html .= '<th style="text-align: left; font-weight: bold;">Equipo/Utensilio</th>';
                        $html .= '<th style="text-align: left; font-weight: bold;">Descripción</th>';
                        //$html .= '<th style="text-align: center; font-weight: bold;">Esencial</th>';
                        $html .= '</tr>';
                        
                        foreach ($equipmentData as $equipment) {
                            $html .= '<tr>';
                            $html .= '<td style="vertical-align: top;">' . htmlspecialchars($equipment['section'] ?? '') . '</td>';
                            $html .= '<td style="vertical-align: top; font-weight: bold;">' . htmlspecialchars($equipment['name'] ?? '') . '</td>';
                            $html .= '<td style="vertical-align: top;">' . htmlspecialchars($equipment['description'] ?? '') . '</td>';
                            
                            // Mostrar si es esencial con un ícono
                            //$isEssential = isset($equipment['essential']) && $equipment['essential'];
                            // $essentialText = $isEssential ? '✓ Sí' : '○ No';
                            //$html .= '<td style="text-align: center; vertical-align: top;">' . $essentialText . '</td>';
                            $html .= '</tr>';
                        }
                        
                        $html .= '</table>';
                    } else {
                        // Si no es JSON válido, mostrar como texto plano
                        $html .= '<p>' . htmlspecialchars($recipe->equipment) . '</p>';
                    }
                } catch (Exception $e) {
                    // En caso de error, mostrar como texto plano
                    $html .= '<p>' . htmlspecialchars($recipe->equipment) . '</p>';
                }
            } else {
                $html .= '<p><em>No se ha especificado equipo para esta receta.</em></p>';
            }
            
            $mpdf->WriteHTML($html);
            // Reiniciar el HTML para la siguiente receta
            $html = '';
        }
    
        // Ya no necesitamos escribir $html aquí, porque lo hemos escrito por cada receta
        
        $fileName = 'Complete_Recipes.pdf';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $mpdf->Output($tempFile, \Mpdf\Output\Destination::FILE);
    
        return Yii::$app->response->sendFile($tempFile, $fileName);
    }

    public function actionExportRecipesToExcel()
    {
        $get = Yii::$app->request->get();
        $selectedRecipes = isset($get['id']) ? explode(',', $get['id']) : []; // Obtener los IDs de las recetas seleccionadas
        $exportAll = isset($get['all']) && $get['all'] === 'true';
        $type = $get['type'];
        $business = \backend\helpers\RedisKeys::getBusiness();
        // Buscar todas las recetas seleccionadas
        $recipes = $exportAll ? StandardRecipe::find()->where(['business_id' => $business->id, 'type' => $type, 'in_construction' => 0])->all() : StandardRecipe::find()->where(['id' => $selectedRecipes])->all();
        
        if (empty($recipes)) {
            throw new \yii\web\NotFoundHttpException('No se encontraron recetas seleccionadas.');
        }
    
        $isSubrecipe = ($type === StandardRecipe::STANDARD_RECIPE_TYPE_SUB);
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // 1. Configurar hojas principales con los mismos nombres que la plantilla
        $recipesSheet = $spreadsheet->getActiveSheet();
        $recipesSheet->setTitle($isSubrecipe ? 'FICHA GENERAL DE LA SUBRECETA' : 'FICHA GENERAL DE LA RECETA');
        
        $ingredientsSheet = $spreadsheet->createSheet();
        $ingredientsSheet->setTitle('INGREDIENTES');
        
        $insumosSheet = $spreadsheet->createSheet();
        $insumosSheet->setTitle('INSUMOS');
        // if ($type === StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
        //     $subrecipesSheet = $spreadsheet->createSheet();
        //     $subrecipesSheet->setTitle('SUBRECETAS');
        // }
        
        
        $convoySheet = $spreadsheet->createSheet();
        $convoySheet->setTitle('CONVOY');
        
        $umSheet = $spreadsheet->createSheet();
        $umSheet->setTitle('UMs');
        
        $categorySheet = $spreadsheet->createSheet();
        $categorySheet->setTitle('Categorias');
    
        // 2. Configurar cabeceras para la hoja principal según el tipo
        $recipesHeaders = $isSubrecipe ? 
            ['Nombre', 'Tipo de Subreceta', 'Tiempo de preparación', 'Unidad de tiempo', 
             'Rendimiento', 'Rendimiento UM', 'Unidad de medida final', 'Porciones', 'Duración', 'Unidad de duración', 'Costo', '% Costo'] :
            ['Nombre', 'Tipo de Receta', 'Tiempo de preparación', 'Unidad de tiempo', 
             'Rendimiento', 'Rendimiento UM', 'Unidad de medida final', 'Porciones', 'Duración', 'Unidad de duración', 
             'Precio', 'Alimento o Bebida', 'Convoy', 'Costo', '% Costo'];
    
        $col = 'A';
        foreach ($recipesHeaders as $header) {
            $recipesSheet->setCellValue($col.'1', $header);
            $recipesSheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $recipesSheet->freezePane('B2');
        // 3. Configurar cabeceras para otras hojas        
        $ingredientsSheet->setCellValue('A1', ($isSubrecipe ? 'SubReceta' : 'Receta'));
        $ingredientsSheet->setCellValue('B1', 'Ingrediente o Subreceta*');
        $ingredientsSheet->setCellValue('C1', 'Cantidad*');
        $ingredientsSheet->setCellValue('D1', 'UM*'); 
        $ingredientsSheet->setCellValue('E1', 'Costo');
        
        $insumosSheet->setCellValue('A1', 'Insumo');
        $insumosSheet->setCellValue('B1', 'Cantidad');
        $insumosSheet->setCellValue('C1', 'UM');
        $insumosSheet->setCellValue('D1', 'Costo');
        
        // if ($type === StandardRecipe::STANDARD_RECIPE_TYPE_MAIN) {
        //     $subrecipesSheet->setCellValue('A1', 'Nombre de la Subreceta');
        //     $subrecipesSheet->setCellValue('B1', 'Tipo de Subreceta');
        //     $subrecipesSheet->setCellValue('C1', 'Tiempo de preparación');
        //     $subrecipesSheet->setCellValue('D1', 'Unidad de tiempo');
        //     $subrecipesSheet->setCellValue('E1', 'Rendimiento');
        //     $subrecipesSheet->setCellValue('F1', 'Rendimiento UM');
        //     $subrecipesSheet->setCellValue('G1', 'Porciones');
        //     $subrecipesSheet->setCellValue('H1', 'Duración');
        //     $subrecipesSheet->setCellValue('I1', 'Unidad de duración');
        //     $subrecipesSheet->setCellValue('J1', 'Unidad de medida final');
        // }
        
        
        $convoySheet->setCellValue('A1', 'ID Convoy');
        $convoySheet->setCellValue('B1', 'Nombre Convoy');
        
        $umSheet->setCellValue('A1', 'Unidad de Medida');
        
        $categorySheet->setCellValue('A1', 'Categoría');
    
        // 4. Ancho automático para todas las columnas
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            foreach (range('A', 'Z') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }
    
        // 5. Cargar datos de referencia (UMs, Categorías, Insumos, Convoy)
        // Unidades de medida
        $unitOfMeasurements = UnitOfMeasurement::find()
            ->select('name')
            ->where(['business_id' => $business->id])
            ->groupBy('name')
            ->all();
        $rowUM = 2;
        foreach ($unitOfMeasurements as $um) {
            $umSheet->setCellValue("A$rowUM", $um->name);
            $rowUM++;
        }
        
        // Categorías
        $categories = RecipeCategory::find()
            ->where(['business_id' => $business->id])
            ->all();
        $rowCategory = 2;
        foreach ($categories as $category) {
            $categorySheet->setCellValue("A$rowCategory", $category->name);
            $rowCategory++;
        }
        
        // Convoy
        $convoys = Convoy::find()
            ->where([
                'business_id' => $business['id']
            ])->all();
            
        $rowConvoy = 2;
        foreach ($convoys as $convoy) {
            $convoySheet->setCellValue("A$rowConvoy", $convoy->id);
            $convoySheet->setCellValue("B$rowConvoy", $convoy->name);
            $rowConvoy++;
        }
        
        // Insumos
        $ingredientStock = IngredientStock::find()
            ->where([
                'business_id' => $business['id']
            ])->all();
            
        $insumosRow = 2;
        foreach ($ingredientStock as $ingredient) {
            $insumosSheet->setCellValue('A' . $insumosRow, $ingredient->ingredient);
            $insumosSheet->setCellValue('B' . $insumosRow, $ingredient->quantity);
            $insumosSheet->setCellValue('C' . $insumosRow, $ingredient->um);
            $insumosSheet->setCellValue('D' . $insumosRow, number_format($ingredient->lastUnitPrice / $ingredient->portions_per_unit, 2, '.', ''));
            $insumosRow++;
        }
    
        // 6. Llenar las datos de recetas con el nuevo formato
        // Build category is_food map
        $_catIsFoodMapExport = [];
        foreach (RecipeCategory::find()->where(['business_id' => $business->id, 'type' => RecipeCategory::TYPE_MAIN])->all() as $_c) {
            $_catIsFoodMapExport[$_c->name] = $_c->is_food;
        }

        $recipesRow = 2;
        $ingredientsRow = 2;
    
        foreach ($recipes as $recipe) {
            // CORRECCIÓN: Mejorar la extracción del tiempo de preparación - más robusto
            $timeValue = '';
            $timeUnit = ''; // Valor predeterminado
    
            if (!empty($recipe->time_of_preparation)) {
                if (preg_match('/^(\d+(?:\.\d+)?)\s*(\w+.*)?$/', trim($recipe->time_of_preparation), $matches)) {
                    $timeValue = $matches[1]; // Valor numérico
                    
                    // Si hay unidad especificada, usarla; si no, usar el predeterminado
                    if (isset($matches[2]) && !empty(trim($matches[2]))) {
                        $unitText = trim(strtolower($matches[2]));
                        
                        // Normalización de unidades
                        if (strpos($unitText, 'min') !== false) {
                            $timeUnit = 'minutos';
                        } elseif (strpos($unitText, 'hor') !== false || strpos($unitText, 'hr') !== false) {
                            $timeUnit = 'horas';
                        } elseif (strpos($unitText, 'día') !== false || strpos($unitText, 'dia') !== false) {
                            $timeUnit = 'días';
                        } else {
                            $timeUnit = $unitText; // Usar tal cual si no coincide con ninguna normalización
                        }
                    }
                } else {
                    // Si no tiene formato numérico, poner el valor completo como numérico
                    $timeValue = $recipe->time_of_preparation;
                }
            }
            
            // CORRECCIÓN: Mejorar la extracción de la duración - más robusto
            $durationValue = '';
           $durationUnit = ''; // Valor predeterminado
    
            if (!empty($recipe->lifetime)) {
                if (preg_match('/^(\d+(?:\.\d+)?)\s*(\w+.*)?$/', trim($recipe->lifetime), $matches)) {
                    $durationValue = $matches[1]; // Valor numérico
                    
                    // Si hay unidad especificada, usarla; si no, usar el predeterminado
                    if (isset($matches[2]) && !empty(trim($matches[2]))) {
                        $unitText = trim(strtolower($matches[2]));
                        
                        // Normalización de unidades
                        if (strpos($unitText, 'min') !== false) {
                            $durationUnit = 'minutos';
                        } elseif (strpos($unitText, 'hor') !== false || strpos($unitText, 'hr') !== false) {
                            $durationUnit = 'horas';
                        } elseif (strpos($unitText, 'día') !== false || strpos($unitText, 'dia') !== false) {
                            $durationUnit = 'días';
                        } else {
                            $durationUnit = $unitText; // Usar tal cual si no coincide con ninguna normalización
                        }
                    }
                } else {
                    // Si no tiene formato numérico, poner el valor completo como numérico
                    $durationValue = $recipe->lifetime;
                }
            }
    
            // Llenar hoja de Recetas en el nuevo formato
            $col = 'A';
            $recipesSheet->setCellValue($col++.$recipesRow, $recipe->title);
            $recipesSheet->setCellValue($col++.$recipesRow, $recipe->type_of_recipe);
            $recipesSheet->setCellValue($col++.$recipesRow, $timeValue);
            $recipesSheet->setCellValue($col++.$recipesRow, $timeUnit);
            $recipesSheet->setCellValue($col++.$recipesRow, $recipe->yield);
            $recipesSheet->setCellValue($col++.$recipesRow, $recipe->yield_um);
            $recipesSheet->setCellValue($col++.$recipesRow, $recipe->um ?: '');
            $recipesSheet->setCellValue($col++.$recipesRow, $recipe->portions);
            $recipesSheet->setCellValue($col++.$recipesRow, $durationValue);
            $recipesSheet->setCellValue($col++.$recipesRow, $durationUnit);
            if (!$isSubrecipe) {
                $recipesSheet->setCellValue($col++.$recipesRow, $recipe->price);
                // Derive is_food from recipe category
                $_isFoodVal = $_catIsFoodMapExport[$recipe->type_of_recipe] ?? null;
                $recipesSheet->setCellValue($col++.$recipesRow, ($_isFoodVal === null) ? '' : ($_isFoodVal ? 'Alimento' : 'Bebida'));
                $recipesSheet->setCellValue($col++.$recipesRow, Convoy::find()->where(['id' => $recipe->convoy_id])->one()->name ?? '');
            }
            // NUEVO: Costo y % Costo
            $recipesSheet->setCellValue($col++.$recipesRow, $recipe->recipeLastPrice);
            $recipesSheet->setCellValue($col++.$recipesRow, $recipe->costPercent * 100);
            // Llenar hoja de Ingredientes
            foreach ($recipe->ingredientRelations as $ingredientRelation) {
                $ingredientsSheet->setCellValue('A'.$ingredientsRow, $recipe->title);
                // Insumos en minúsculas
                $ingredientsSheet->setCellValue('B'.$ingredientsRow, strtolower($ingredientRelation->ingredient->ingredient));
                $ingredientsSheet->setCellValue('C'.$ingredientsRow, $ingredientRelation->quantity);
                $ingredientsSheet->setCellValue('D'.$ingredientsRow, $ingredientRelation->ingredient->portion_um);
                // preservar la precisión sin formatear ni redondear
                $cost = bcmul((string)$ingredientRelation->lastUnitPrice, (string)$ingredientRelation->quantity, 10);
                $ingredientsSheet->setCellValue('E'.$ingredientsRow, $cost);
                $ingredientsRow++;
            }
           //die(var_dump($recipe->getSubStandardRecipes()->all()));
            foreach ($recipe->getSubStandardRecipes()->all() as $subrecipeRelation) {
                $ingredientsSheet->setCellValue('A'.$ingredientsRow, $recipe->title);
                //die(var_dump($subrecipeRelation->title));
                // Subrecetas en MAYÚSCULAS
                $ingredientsSheet->setCellValue('B'.$ingredientsRow, strtoupper($subrecipeRelation->title));
                // die(var_dump($recipe->id));
                $ingredientsSheet->setCellValue('C'.$ingredientsRow, $subrecipeRelation->getQuantityLinked($recipe->id));
                $ingredientsSheet->setCellValue('D'.$ingredientsRow, $subrecipeRelation->um);
                $ingredientsSheet->setCellValue('E'.$ingredientsRow, $subrecipeRelation->custom_cost*$subrecipeRelation->getQuantityLinked($recipe->id));
                $ingredientsRow++;
            }
            
            $recipesRow++;
        }
    
        // 8. Estilo y formato
        $centerStyle = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];
        
        // CORRECCIÓN: Ajustar el rango de estilo para incluir todas las columnas
        $lastCol = $isSubrecipe ? 'L' : 'O'; // Última columna según el tipo (2 columnas más)
        $recipesSheet->getStyle('A1:' . $lastCol . ($recipesRow-1))->applyFromArray($centerStyle);
        $ingredientsSheet->getStyle('A1:E' . ($ingredientsRow-1))->applyFromArray($centerStyle);
        
        // Formato de moneda para precio
        if (!$isSubrecipe) {
            $recipesSheet->getStyle('K2:K' . ($recipesRow-1))->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $recipesSheet->getStyle('N2:N' . ($recipesRow-1))->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $recipesSheet->getStyle('O2:O' . ($recipesRow-1))->getNumberFormat()
                ->setFormatCode('0.00"%"');
        } else {
            $recipesSheet->getStyle('K2:K' . ($recipesRow-1))->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $recipesSheet->getStyle('L2:L' . ($recipesRow-1))->getNumberFormat()
                ->setFormatCode('0.00"%"');
        }
        
        // Formato de moneda para costos en la hoja de ingredientes
        $ingredientsSheet->getStyle('E2:E' . ($ingredientsRow-1))->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
    
        // 9. Agregar fórmulas para insumos similares a la plantilla
        // for ($i = 2; $i <= 100; $i++) {
        //     $ingredientsSheet->setCellValue("D$i", "=IFERROR(VLOOKUP(B$i, INSUMOS!A:D, 3, FALSE), \"\")");
        //     $ingredientsSheet->setCellValue("E$i", "=IF(IFERROR(C$i * VLOOKUP(B$i, INSUMOS!A:D, 4, FALSE), \"\")=\"\",\"\",ROUND(C$i * VLOOKUP(B$i, INSUMOS!A:D, 4, FALSE), 2))");
        // }
    
        // 10. Añadir nota informativa similar a la plantilla
        $noteText = ($type === 'sub') 
        ? 'NOTA: El nombre de la subreceta debe existir primero en la hoja "FICHA GENERAL DE LA SUBRECETA"' 
        : 'NOTA: El nombre de la receta debe existir primero en la hoja "FICHA GENERAL DE LA RECETA"';
        $ingredientsSheet->setCellValue('G1', $noteText);
        $ingredientsSheet->mergeCells('G1:O1');
        $ingredientsSheet->getStyle('G1')->getFont()
            ->setItalic(true)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKRED));
        $spreadsheet->setActiveSheetIndex(0); // Esto establece la primera hoja como activa
        
        // Establecer la celda A2 como celda activa al abrir el archivo
        $spreadsheet->getActiveSheet()->setSelectedCell('A2');
        // Generar el archivo Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = $isSubrecipe ? 'Subrecetas_Exportadas.xlsx' : 'Recetas_Exportadas.xlsx';
    
        // Crear archivo temporal y enviarlo correctamente
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Yii::$app->response->sendFile($tempFile, $fileName);
    }

 public function actionExportRecipesPlantilla($type)
 {
     $business = \backend\helpers\RedisKeys::getBusiness();
     // Configuración para mejorar rendimiento
     set_time_limit(300);
     ini_set('memory_limit', '512M');
     // Configurar títulos según el tipo
    $mainTitle = ($type === 'sub') ? 'FICHA GENERAL DE LA SUBRECETA' : 'FICHA GENERAL DE LA RECETA';
    $ingredientsTitle = ($type === 'sub') ? 'INGREDIENTES PARA SUBRECETA' : 'INGREDIENTES';
     $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
     
     // 1. Configurar hojas principales
     $recipesSheet = $spreadsheet->getActiveSheet();
     $recipesSheet->setTitle($mainTitle);
     
     // 2. Modificar cabeceras de recetas (separar duración y tiempo de preparación)
     // Configurar headers según el tipo
    $recipesHeaders = ($type === 'sub') 
    ? [
        'Nombre*', 
        'Tipo de Subreceta*', 
        'Tiempo de preparación', 
        'Unidad de tiempo', 
        'Rendimiento*', 
        'Rendimiento UM*',
        'Unidad de medida final*',
        'Porciones*', 
        'Duración', 
        'Unidad de duración'
      ]
    : [
        'Nombre*', 
        'Tipo de Receta*', 
        'Tiempo de preparación', 
        'Unidad de tiempo', 
        'Rendimiento*', 
        'Rendimiento UM*',
        'Unidad de medida final*', 
        'Porciones*', 
        'Duración', 
        'Unidad de duración', 
        'Precio*', 
        'Convoy'
        
      ];
     
     $col = 'A';
     foreach ($recipesHeaders as $header) {
         $recipesSheet->setCellValue($col.'1', $header);
         $recipesSheet->getColumnDimension($col)->setAutoSize(true);
         $col++;
     }
       // 3. Crear otras hojas necesarias
     $ingredientsSheet = $spreadsheet->createSheet();
     $ingredientsSheet->setTitle('INGREDIENTES');
     
     // Add type validation for INGREDIENTES sheet
     $typeValidation = $ingredientsSheet->getCell('B2')->getDataValidation();
     $typeValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
     $typeValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
     $typeValidation->setAllowBlank(false);
     $typeValidation->setShowInputMessage(true);
     $typeValidation->setShowErrorMessage(true);
     $typeValidation->setShowDropDown(true);
     $typeValidation->setErrorTitle('Error de entrada');
     $typeValidation->setError('Seleccione INSUMO o SUBRECETA');
     $typeValidation->setPromptTitle('Tipo de ingrediente');
     $typeValidation->setPrompt('Seleccione si es un insumo o una subreceta');
     $typeValidation->setFormula1('"INSUMO,SUBRECETA"');

     // Apply type validation to type column
     for ($i = 2; $i <= 500; $i++) {
         $ingredientsSheet->getCell("B$i")->setDataValidation(clone $typeValidation);
     }
     
     $insumosSheet = $spreadsheet->createSheet();
     $insumosSheet->setTitle('INSUMOS');
     
     $subrecipesSheet = $spreadsheet->createSheet();
     $subrecipesSheet->setTitle('SUBRECETAS');
     
     $convoySheet = $spreadsheet->createSheet();
     $convoySheet->setTitle('CONVOY');
     
     $umSheet = $spreadsheet->createSheet();
     $umSheet->setTitle('UMs');
     
     $categorySheet = $spreadsheet->createSheet();
     $categorySheet->setTitle('Categorias');
    
     // 4. Configurar cabeceras de todas las hojas
     $headers = [
         'INGREDIENTES' => [($type === 'sub' ? 'SubReceta' : 'Receta'), 'Insumo', 'Cantidad', 'UM', 'Costo'],
         'INSUMOS' => ['Insumo', 'Cantidad', 'UM', 'Costo'],
         'CONVOY' => ['ID Convoy', 'Nombre Convoy'],
         'UMs' => ['Unidad de Medida'],
         'Categorias' => ['Categoría'],
         'SUBRECETAS' => ['Nombre de la Subreceta', 'Porciones','Unidad de medida final','Costo']
     ];
       // Modify INGREDIENTES headers to include type selector
     $headers['INGREDIENTES'] = [
        ($type === 'sub' ? 'SubReceta' : 'Receta'),
        'Tipo*',
        'Item*',
        'Cantidad*', 
        'UM', 
        'Costo'
    ];
     
     foreach ($headers as $sheetName => $sheetHeaders) {
         $sheet = $spreadsheet->getSheetByName($sheetName);
         $col = 'A';
         foreach ($sheetHeaders as $header) {
             $sheet->setCellValue($col.'1', $header);
             //$sheet->getColumnDimension($col)->setAutoSize(true)->setWidth(50);
             $sheet->getStyle($col)->getAlignment()->setWrapText(true);
             $col++;
         }
         $sheet->freezePane('A2');
     }
    // Configuración especial para columnas (aplicar después del bucle de cabeceras para que no se sobrescriba)
    $ingredientsSheet->getColumnDimension('B')->setAutoSize(true)->setWidth(30);
    $ingredientsSheet->getColumnDimension('C')->setWidth(20);
    $ingredientsSheet->getColumnDimension('D')->setWidth(15);
    $ingredientsSheet->getColumnDimension('F')->setWidth(15);
    // Centrar el header y los datos de la columna F
    $ingredientsSheet->getStyle('F1:F500')->applyFromArray([
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
    ]);
    $ingredientsSheet->getStyle('B2:B500')->getAlignment()->setWrapText(true);
    $ingredientsSheet->getColumnDimension('A')->setAutoSize(true)->setWidth(30);
    $ingredientsSheet->getStyle('A2:A500')->getAlignment()->setWrapText(true);
    $recipesSheet->getColumnDimension('A')->setAutoSize(true)->setWidth(30);
    $recipesSheet->getStyle('A2:A500')->getAlignment()->setWrapText(true);
     
     // 5. Cargar datos con límite para la plantilla
     $batchSize = 350;
     
     // Separar unidades de medida por tipo
     $subrecipeYieldUMs = UnitOfMeasurement::find()
         ->select('name')
         ->where(['business_id' => $business['id'], 'is_subrecipe_yield' => 1])
         ->groupBy('name')
         ->limit($batchSize)
         ->all();
     
     $subrecipeUMs = UnitOfMeasurement::find()
         ->select('name')
         ->where(['business_id' => $business['id'], 'is_subrecipe_um' => 1])
         ->groupBy('name')
         ->limit($batchSize)
         ->all();
     
     $recipeYieldUMs = UnitOfMeasurement::find()
         ->select('name')
         ->where(['business_id' => $business['id'], 'is_recipe_yield' => 1])
         ->groupBy('name')
         ->limit($batchSize)
         ->all();
     
     $recipeUMs = UnitOfMeasurement::find()
         ->select('name')
         ->where(['business_id' => $business['id'], 'is_recipe_final_um' => 1])
         ->groupBy('name')
         ->limit($batchSize)
         ->all();
     
     $categories = RecipeCategory::find()
         ->where(['business_id' => $business['id']])
         ->andWhere(['type' => $type])
         ->limit($batchSize)
         ->all();
     
     $ingredientStock = IngredientStock::find()
         ->where(['business_id' => $business['id']])
         ->all();
     
     $convoy = Convoy::find()
         ->where(['business_id' => $business['id']])
         ->limit($batchSize)
         ->all();
    $subrecetas = StandardRecipe::find()
         ->where(['business_id' => $business['id']])
         ->andWhere(['type' => \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_SUB])
         ->limit($batchSize)
         ->all();
     
     // 6. Llenar hojas de referencia
     // Unidades de medida - combinar todas las categorías para la hoja UMs
     $allUMs = array_unique(array_merge(
         array_column($subrecipeYieldUMs, 'name'),
         array_column($subrecipeUMs, 'name'),
         array_column($recipeYieldUMs, 'name'),
         array_column($recipeUMs, 'name')
     ));
     
     $rowUM = 2;
     foreach ($allUMs as $umName) {
         $umSheet->setCellValue("A$rowUM", $umName);
         $rowUM++;
     }
     
     $rowCategory = 2;
     foreach ($categories as $category) {
         $categorySheet->setCellValue("A$rowCategory", $category->name);
         $rowCategory++;
     }
     
     $rowConvoy = 2;
     foreach ($convoy as $conv) {
         $convoySheet->setCellValue("B$rowConvoy", $conv->name);
         $rowConvoy++;
     }
     
     $insumosRow = 2;
     foreach ($ingredientStock as $ingredient) {
         // Insumos en minúsculas
         $insumosSheet->setCellValue('A'.$insumosRow, strtolower($ingredient->ingredient));
         $insumosSheet->setCellValue('B'.$insumosRow, $ingredient->quantity);
         $insumosSheet->setCellValue('C'.$insumosRow, $ingredient->portion_um);
         $insumosSheet->setCellValue('D'.$insumosRow, number_format($ingredient->lastUnitPrice, 2, '.', ''));
         $insumosRow++;
     }
      $subrecetaRow = 2;
     foreach ($subrecetas as $subreceta) {
        // Subrecetas en MAYÚSCULAS
        $subrecipesSheet->setCellValue('A'.$subrecetaRow, strtoupper($subreceta->title));
        $subrecipesSheet->setCellValue('B'.$subrecetaRow, $subreceta->portions);
        $subrecipesSheet->setCellValue('C'.$subrecetaRow, $subreceta->um);
        $subrecipesSheet->setCellValue('D'.$subrecetaRow, $subreceta->custom_cost);
        $subrecetaRow++;
    }
     
     // 7. Configurar estilos
     $centerStyle = [
         'alignment' => [
             'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
             'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
         ],
     ];
     
     $recipesSheet->getStyle('A1:L300')->applyFromArray($centerStyle);
     $ingredientsSheet->getStyle('A1:E500')->applyFromArray($centerStyle);
     $insumosSheet->getStyle('A1:D350')->applyFromArray($centerStyle);
     $convoySheet->getStyle('A1:B100')->applyFromArray($centerStyle);
     $umSheet->getStyle('A1:A100')->applyFromArray($centerStyle);
     $categorySheet->getStyle('A1:A100')->applyFromArray($centerStyle);
     $recipesSheet->getColumnDimension('J')->setWidth(15);

    $recipesSheet->freezePane('B2');    // Fijar fila 1 y columna A
    $ingredientsSheet->freezePane('B2'); // Fijar fila 1 y columna A
    $insumosSheet->freezePane('B2');  
     
     // 8. Configurar TODAS las validaciones optimizadas
     $colFinalUM = 'G'; // Ajusta estas letras según tu estructura de columnas
     
     // Determinar qué unidades usar según el tipo
     $finalUMs = ($type === 'sub') ? $subrecipeUMs : $recipeUMs;
     $yieldUMs = ($type === 'sub') ? $subrecipeYieldUMs : $recipeYieldUMs;

$dataValidationFinalUM = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
$dataValidationFinalUM->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
$dataValidationFinalUM->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
$dataValidationFinalUM->setAllowBlank(false);
$dataValidationFinalUM->setShowInputMessage(true);
$dataValidationFinalUM->setShowErrorMessage(true);
$dataValidationFinalUM->setShowDropDown(true);
$dataValidationFinalUM->setErrorTitle('Error de entrada');
$dataValidationFinalUM->setError('Seleccione una unidad de medida válida');
$dataValidationFinalUM->setPromptTitle('Unidad de medida final');
$dataValidationFinalUM->setPrompt('Seleccione la unidad de medida final para esta ' . ($type === 'sub' ? 'subreceta' : 'receta'));

// Crear una hoja temporal para las unidades específicas si no están vacías
if (!empty($finalUMs)) {
    $finalUMNames = array_column($finalUMs, 'name');
    $finalUMList = '"' . implode(',', $finalUMNames) . '"';
    $dataValidationFinalUM->setFormula1($finalUMList);
} else {
    $dataValidationFinalUM->setFormula1('""');
}

// 2. Aplicar a todas las filas
for ($i = 2; $i <= 500; $i++) {
    $recipesSheet->getCell($colFinalUM.$i)->setDataValidation(clone $dataValidationFinalUM);
}

// 3. Ajustar ancho de columna si es necesario
$recipesSheet->getColumnDimension($colFinalUM)->setWidth(20);
     // a) Validación para nombres de recetas en INGREDIENTES
     $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
     $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
     $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
     $validation->setAllowBlank(false);
     $validation->setShowInputMessage(true);
     $validation->setShowErrorMessage(true);
     $validation->setShowDropDown(true);
     $validation->setErrorTitle('Error de entrada');
     $validation->setError('Debe seleccionar una receta existente');
     $validation->setPromptTitle('Seleccionar receta');
     $validation->setPrompt('Seleccione una receta de la lista');
    
        
    // Usar una fórmula dinámica que se actualice automáticamente cuando se añaden elementos
    if ($type === 'sub') {
        $sheetName = 'FICHA GENERAL DE LA SUBRECETA';
    } else {
        $sheetName = 'FICHA GENERAL DE LA RECETA';
    }

    // Fórmula mejorada que funciona incluso cuando no hay datos inicialmente
    $dynamicFormula = "=OFFSET('$sheetName'!A\$2,0,0,COUNTA('$sheetName'!A:A)-1,1)";
    $validation->setFormula1($dynamicFormula);
     
     // Aplicar a 500 filas para permitir múltiples ingredientes por receta
     for ($row = 2; $row <= 500; $row++) {
         $ingredientsSheet->getCell('A'.$row)->setDataValidation(clone $validation);
     }
     
     // c) Validación para unidades de medida (UM) en INGREDIENTES - usar todas las unidades disponibles
     $dataValidationUM = $ingredientsSheet->getCell('E2')->getDataValidation();
     $dataValidationUM->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
     $dataValidationUM->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
     $dataValidationUM->setAllowBlank(false);
     $dataValidationUM->setShowInputMessage(true);
     $dataValidationUM->setShowErrorMessage(true);
     $dataValidationUM->setShowDropDown(true);
     $dataValidationUM->setErrorTitle('Error de entrada');
     $dataValidationUM->setError('Este valor no es admitido');
     $dataValidationUM->setPromptTitle('Selecciona una unidad de medida');
     $dataValidationUM->setPrompt('Por favor, selecciona un valor del desplegable.');
     
     // Usar todas las unidades disponibles para ingredientes
     if (!empty($allUMs)) {
         $allUMList = '"' . implode(',', $allUMs) . '"';
         $dataValidationUM->setFormula1($allUMList);
     } else {
         $dataValidationUM->setFormula1('""');
     }
     
     // d) Validación para categorías
     $dataValidationCategory = $recipesSheet->getCell('B2')->getDataValidation();
     $dataValidationCategory->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
     $dataValidationCategory->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
     $dataValidationCategory->setAllowBlank(false);
     $dataValidationCategory->setShowInputMessage(true);
     $dataValidationCategory->setShowErrorMessage(true);
     $dataValidationCategory->setShowDropDown(true);
     $dataValidationCategory->setErrorTitle('Error de entrada');
     $dataValidationCategory->setError('Este valor no es admitido');
     $dataValidationCategory->setPromptTitle('Selecciona una categoría');
     $dataValidationCategory->setPrompt('Por favor, selecciona un valor del desplegable.');
     $dataValidationCategory->setFormula1('=Categorias!$A$2:$A$' . ($rowCategory - 1));
     
     // e) Validación para rendimiento (sólo números)
     $dataValidationYield = $recipesSheet->getCell('E2')->getDataValidation();
     $dataValidationYield->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
     $dataValidationYield->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
     $dataValidationYield->setAllowBlank(false);
     $dataValidationYield->setShowInputMessage(true);
     $dataValidationYield->setShowErrorMessage(true);
     $dataValidationYield->setErrorTitle('Error de entrada');
     $dataValidationYield->setError('Este campo solo acepta valores numéricos');
     $dataValidationYield->setPromptTitle('Ingrese el rendimiento');
     $dataValidationYield->setPrompt('Por favor, ingrese un valor numérico para el rendimiento.');
     $dataValidationYield->setFormula1(0);
     $dataValidationYield->setFormula2(999999);
     
     // f) Validación para convoy (ahora en columna L, ya que Alimento/Bebida fue eliminado)
     if ($type !== 'sub') {
         $dataValidationConvoy = $recipesSheet->getCell('L2')->getDataValidation();
         $dataValidationConvoy->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
         $dataValidationConvoy->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
         $dataValidationConvoy->setAllowBlank(false);
         $dataValidationConvoy->setShowInputMessage(true);
         $dataValidationConvoy->setShowErrorMessage(true);
         $dataValidationConvoy->setShowDropDown(true);
         $dataValidationConvoy->setErrorTitle('Error de entrada');
         $dataValidationConvoy->setError('Este valor no es admitido');
         $dataValidationConvoy->setPromptTitle('Selecciona un convoy');
         $dataValidationConvoy->setPrompt('Por favor, selecciona un valor del desplegable.');
         if ($rowConvoy > 2) {
             // If there are convoy items, use them for validation
             $dataValidationConvoy->setFormula1('=CONVOY!$B$2:$B$' . ($rowConvoy - 1));
         } else {
             // If no convoy items, use an empty list
             $dataValidationConvoy->setFormula1('""');
         }
     }
    //  $dataValidationConvoy = $recipesSheet->getCell('L2')->getDataValidation();
    //  $dataValidationConvoy->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
    //  $dataValidationConvoy->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
    //  $dataValidationConvoy->setAllowBlank(false);
    //  $dataValidationConvoy->setShowInputMessage(true);
    //  $dataValidationConvoy->setShowErrorMessage(true);
    //  $dataValidationConvoy->setShowDropDown(true);
    //  $dataValidationConvoy->setErrorTitle('Error de entrada');
    //  $dataValidationConvoy->setError('Este valor no es admitido');
    //  $dataValidationConvoy->setPromptTitle('Selecciona un convoy');
    //  $dataValidationConvoy->setPrompt('Por favor, selecciona un valor del desplegable.');
    // if ($rowConvoy > 2) {
    //     // If there are convoy items, use them for validation
    //     $dataValidationConvoy->setFormula1('=CONVOY!$B$2:$B$' . ($rowConvoy - 1));
    // } else {
    //     // If no convoy items, use an empty list
    //     $dataValidationConvoy->setFormula1('""');
    // }
   // Calculate actual last rows for direct range references (no INDIRECT, no COUNTA)
    $lastInsumoRow = $insumosRow - 1;
    $lastSubRow = $subrecetaRow - 1;
    // Build range references for data validation
    $insumosRange = ($lastInsumoRow >= 2) ? "INSUMOS!\$A\$2:\$A\$$lastInsumoRow" : "";
    $subrecetasRange = ($lastSubRow >= 2) ? "SUBRECETAS!\$A\$2:\$A\$$lastSubRow" : "";

   for ($i = 2; $i <= 500; $i++) {
     // Dejar la celda vacía para que el usuario seleccione del dropdown manualmente
     // Configurar la validación de datos para la columna Item
     $itemValidation = $ingredientsSheet->getCell("C$i")->getDataValidation();
     $itemValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
     $itemValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
     $itemValidation->setAllowBlank(false);
     $itemValidation->setShowInputMessage(true);
     $itemValidation->setShowErrorMessage(true);
     $itemValidation->setShowDropDown(true);
     $itemValidation->setErrorTitle('Error de entrada');
     $itemValidation->setError('Seleccione un item válido');
     $itemValidation->setPromptTitle('Seleccionar item');
     $itemValidation->setPrompt('Seleccione un insumo o subreceta según el tipo');
     // Use direct range references instead of INDIRECT+COUNTA for better compatibility
     if (!empty($insumosRange) && !empty($subrecetasRange)) {
         $itemValidation->setFormula1("=IF(B$i=\"INSUMO\",$insumosRange,$subrecetasRange)");
     } elseif (!empty($insumosRange)) {
         $itemValidation->setFormula1("=$insumosRange");
     } elseif (!empty($subrecetasRange)) {
         $itemValidation->setFormula1("=$subrecetasRange");
     } else {
         $itemValidation->setFormula1('""');
     }
     
     $ingredientsSheet->getCell("C$i")->setDataValidation($itemValidation);
      // Fórmulas corregidas para UM y costo
      $ingredientsSheet->setCellValue(
         "E$i", 
         "=IF(B$i=\"INSUMO\",IFERROR(VLOOKUP(C$i,INSUMOS!A:D,3,FALSE),\"\"),IF(B$i=\"SUBRECETA\",IFERROR(VLOOKUP(C$i,SUBRECETAS!A:D,3,FALSE),\"\"),\"\"))"
     );

    $ingredientsSheet->setCellValue(
        "F$i", 
        "=IF(B$i=\"INSUMO\",
            IF(D$i=\"\",\"\",
                IFERROR(TEXT(D$i*IFERROR(VLOOKUP(C$i,INSUMOS!A:D,4,FALSE),0),\"0.00\"),\"\")
            ),
            IF(B$i=\"SUBRECETA\",
                IF(D$i=\"\",\"\",
                    IFERROR(TEXT(D$i*IFERROR(VLOOKUP(C$i,SUBRECETAS!A:D,4,FALSE),0),\"0.00\"),\"\")
                ),
                \"\"
            )
        )"
    );
}
     // h) Validación para unidades de tiempo (duración y tiempo de preparación)
     $dataValidationTimeUnits = $recipesSheet->getCell('D2')->getDataValidation();
     $dataValidationTimeUnits->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
     $dataValidationTimeUnits->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
     $dataValidationTimeUnits->setAllowBlank(false);
     $dataValidationTimeUnits->setShowInputMessage(true);
     $dataValidationTimeUnits->setShowErrorMessage(true);
     $dataValidationTimeUnits->setShowDropDown(true);
     $dataValidationTimeUnits->setErrorTitle('Error de entrada');
     $dataValidationTimeUnits->setError('Este valor no es admitido');
     $dataValidationTimeUnits->setPromptTitle('Selecciona una unidad de tiempo');
     $dataValidationTimeUnits->setPrompt('Por favor, selecciona una unidad de tiempo.');
     $dataValidationTimeUnits->setFormula1('"minutos,horas,días"');
     
     // Validación para valores numéricos en tiempo de preparación
     $dataValidationTimeValue = $recipesSheet->getCell('C2')->getDataValidation();
     $dataValidationTimeValue->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
     $dataValidationTimeValue->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
     $dataValidationTimeValue->setAllowBlank(false);
     $dataValidationTimeValue->setShowInputMessage(true);
     $dataValidationTimeValue->setShowErrorMessage(true);
     $dataValidationTimeValue->setErrorTitle('Error de entrada');
     $dataValidationTimeValue->setError('Este campo solo acepta valores numéricos');
     $dataValidationTimeValue->setPromptTitle('Ingrese el valor');
     $dataValidationTimeValue->setPrompt('Por favor, ingrese un valor numérico para el tiempo de preparación');
     $dataValidationTimeValue->setFormula1(0);
     $dataValidationTimeValue->setFormula2(999999);
     
     // Validación para valores numéricos en duración
     $dataValidationDurationValue = $recipesSheet->getCell('H2')->getDataValidation();
     $dataValidationDurationValue->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
     $dataValidationDurationValue->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
     $dataValidationDurationValue->setAllowBlank(false);
     $dataValidationDurationValue->setShowInputMessage(true);
     $dataValidationDurationValue->setShowErrorMessage(true);
     $dataValidationDurationValue->setErrorTitle('Error de entrada');
     $dataValidationDurationValue->setError('Este campo solo acepta valores numéricos');
     $dataValidationDurationValue->setPromptTitle('Ingrese el valor');
     $dataValidationDurationValue->setPrompt('Por favor, ingrese un valor numérico para la duración');
     $dataValidationDurationValue->setFormula1(0);
     $dataValidationDurationValue->setFormula2(999999);
    if ($type !== 'sub'){
     // Modificar la validación para permitir valores más grandes
     $dataValidationPrice = $recipesSheet->getCell('J2')->getDataValidation();
     $dataValidationPrice->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
     $dataValidationPrice->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
     $dataValidationPrice->setAllowBlank(true);
     $dataValidationPrice->setShowInputMessage(true);
     $dataValidationPrice->setShowErrorMessage(true);
     $dataValidationPrice->setErrorTitle('Error de entrada');
     $dataValidationPrice->setError('Este campo solo acepta valores numéricos');
     $dataValidationPrice->setPromptTitle('Ingrese el precio');
     $dataValidationPrice->setPrompt('Por favor, ingrese un valor numérico para el precio');
     $dataValidationPrice->setFormula1(0);
     $dataValidationPrice->setFormula2(99999999); // Aumentar el límite máximo

     $recipesSheet->getStyle('J2:J100')->getNumberFormat()->setFormatCode('$#,##0.00');
    }
     // Aplicar todas las validaciones a las celdas correspondientes
     for ($i = 2; $i <= 500; $i++) {         // Hoja INGREDIENTES
         $ingredientsSheet->getCell("E$i")->setDataValidation(clone $dataValidationUM);
         
         // Hoja RECETAS
         $recipesSheet->getCell("B$i")->setDataValidation(clone $dataValidationCategory);
         
         // Validación específica para rendimiento (columna F) según el tipo
         if (!empty($yieldUMs)) {
             $yieldUMNames = array_column($yieldUMs, 'name');
             $yieldUMList = '"' . implode(',', $yieldUMNames) . '"';
             $dataValidationYieldUM = $recipesSheet->getCell("F$i")->getDataValidation();
             $dataValidationYieldUM->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
             $dataValidationYieldUM->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
             $dataValidationYieldUM->setAllowBlank(false);
             $dataValidationYieldUM->setShowInputMessage(true);
             $dataValidationYieldUM->setShowErrorMessage(true);
             $dataValidationYieldUM->setShowDropDown(true);
             $dataValidationYieldUM->setErrorTitle('Error de entrada');
             $dataValidationYieldUM->setError('Seleccione una unidad de medida de rendimiento válida');
             $dataValidationYieldUM->setPromptTitle('Unidad de rendimiento');
             $dataValidationYieldUM->setPrompt('Seleccione la unidad de medida para el rendimiento');
             $dataValidationYieldUM->setFormula1($yieldUMList);
             $recipesSheet->getCell("F$i")->setDataValidation($dataValidationYieldUM);
         }
         
         $recipesSheet->getCell("E$i")->setDataValidation(clone $dataValidationYield);
         if ($type !== 'sub') {
            $recipesSheet->getCell("L$i")->setDataValidation(clone $dataValidationConvoy);
         }
         $recipesSheet->getCell("D$i")->setDataValidation(clone $dataValidationTimeUnits);
         $recipesSheet->getCell("J$i")->setDataValidation(clone $dataValidationTimeUnits);
         $recipesSheet->getCell("C$i")->setDataValidation(clone $dataValidationTimeValue);
         $recipesSheet->getCell("I$i")->setDataValidation(clone $dataValidationDurationValue);
         if ($type !== 'sub') {
             $recipesSheet->getCell("K$i")->setDataValidation(clone $dataValidationPrice);
         }
     }
     
     // 9. Configurar fórmulas
     
     // Para el cálculo automático del costo y UM en INGREDIENTES (limitando a 2 decimales)
     for ($i = 2; $i <= 500; $i++) {         // Modificar las fórmulas para que muestren cadena vacía en lugar de cero    // Fórmula para la unidad de medida (E)
    // Para INSUMO usa la columna 3 de INSUMOS, para SUBRECETA usa la columna 3 de SUBRECETAS
    $ingredientsSheet->setCellValue("E$i", 
        "=IF(B$i=\"INSUMO\",IFERROR(VLOOKUP(C$i,INSUMOS!A:D,3,FALSE),\"\"),IF(B$i=\"SUBRECETA\",IFERROR(VLOOKUP(C$i,SUBRECETAS!A:D,3,FALSE),\"\"),\"\"))"
    );

    // Fórmula para el costo (F)
    // Para INSUMO usa la columna 4 de INSUMOS, para SUBRECETA usa la columna 4 de SUBRECETAS
    $ingredientsSheet->setCellValue("F$i",
        "=IF(B$i=\"INSUMO\",IF(D$i=\"\",\"\",IFERROR(D$i*VLOOKUP(C$i,INSUMOS!A:D,4,FALSE),\"\")),IF(B$i=\"SUBRECETA\",IF(D$i=\"\",\"\",IFERROR(D$i*VLOOKUP(C$i,SUBRECETAS!A:D,4,FALSE),\"\")),\"\"))"
    );

    // Dar formato de moneda a la columna de costo
    $ingredientsSheet->getStyle("F$i")->getNumberFormat()->setFormatCode('$#,##0.00');

     }
     
     // 11. Añadir fórmula para bloquear el campo de porciones cuando se seleccionan ciertos rendimientos
    for ($i = 2; $i <= 500; $i++) {
        // Determinar qué columna contiene la unidad de medida final según el tipo
        $finalUnitColumn = 'G';

        // Bloquear el campo de porciones hasta que ambas unidades de medida estén completas
        // y luego aplicar la lógica de bloqueo si son iguales (excepto pieza)
        $formulaLockPortions = "=IF(OR(F$i=\"\", $finalUnitColumn$i=\"\"), \"\", IF(AND(F$i=$finalUnitColumn$i, F$i<>\"pieza\", F$i<>\"Pieza\"), 1, \"\"))";
        $recipesSheet->setCellValue("H$i", $formulaLockPortions);
        
        // Validación dinámica para el campo Porciones
        $portionsValidation = $recipesSheet->getCell("H$i")->getDataValidation();
        $portionsValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_CUSTOM);
        $portionsValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $portionsValidation->setAllowBlank(false);
        $portionsValidation->setShowInputMessage(true);
        $portionsValidation->setShowErrorMessage(true);
        $portionsValidation->setErrorTitle('Campo bloqueado');
        $portionsValidation->setError('El campo Porciones está bloqueado hasta que especifique ambas unidades de medida');
        $portionsValidation->setPromptTitle('Porciones');
        $portionsValidation->setPrompt('Ingrese el número de porciones después de completar las unidades de medida');
        
        // La fórmula valida si puede ser editado (TRUE) o no (FALSE)
        // Solo será editable si ambas unidades están definidas Y no son iguales (excepto "pieza")
        $portionsValidation->setFormula1("=IF(OR(F$i=\"\", $finalUnitColumn$i=\"\"), FALSE, IF(AND(F$i=$finalUnitColumn$i, NOT(OR(LOWER(F$i)=\"pieza\", LOWER(F$i)=\"Pieza\"))), FALSE, TRUE))");
        $recipesSheet->getCell("H$i")->setDataValidation($portionsValidation);
    }
     // 12. Nota informativa
    $noteText = ($type === 'sub') 
        ? 'NOTA: El nombre de la subreceta debe existir primero en la hoja "FICHA GENERAL DE LA SUBRECETA"' 
        : 'NOTA: El nombre de la receta debe existir primero en la hoja "FICHA GENERAL DE LA RECETA"';
    $ingredientsSheet->setCellValue('G1', $noteText);
     $ingredientsSheet->mergeCells('G1:Q1');
     $ingredientsSheet->getStyle('G1')->getFont()
         ->setItalic(true)
         ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKRED));
     
     
     // 14. Ajustar anchos de columna
     foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
         $worksheet->calculateColumnWidths();
     }
     // Aplicar formato visual a columnas E y F pero mantener las fórmulas
     $fillColor = 'e5e5e5';
     $borderColor = 'bfbfbf';
     $ingredientsSheet->getStyle('E2:F500')->applyFromArray([
         'fill' => [
             'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
             'startColor' => ['rgb' => $fillColor],
         ],
         'borders' => [
             'allBorders' => [
                 'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                 'color' => ['rgb' => $borderColor],
             ],
         ],
     ]);
     // Las fórmulas permanecen en las celdas para que se recalculen automáticamente

    // 1. Desbloquear todas las celdas primero (por si acaso)
    $ingredientsSheet->getStyle('A2:Q500')->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);

    // 2. Volver a bloquear y ocultar columnas E y F (de la 2 a la 500)
    $ingredientsSheet->getStyle('E2:E500')->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_PROTECTED);
    $ingredientsSheet->getStyle('E2:E500')->getProtection()->setHidden(true);
    $ingredientsSheet->getStyle('F2:F500')->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_PROTECTED);
    $ingredientsSheet->getStyle('F2:F500')->getProtection()->setHidden(true);

    // 3. Proteger la hoja de ingredientes al final, solo si no está protegida
    if (!$ingredientsSheet->getProtection()->getSheet()) {
        $ingredientsSheet->getProtection()->setPassword('anansi'); // Puedes cambiar la contraseña
        $ingredientsSheet->getProtection()->setSheet(true);
    }

    // 4. Forzar el recálculo de fórmulas antes de guardar
    $spreadsheet->setActiveSheetIndex(0);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(true);
    // 15. Definir la primera hoja como activa al abrir el archivo
     $spreadsheet->setActiveSheetIndex(0);
     
     // Establecer la celda A2 como celda activa al abrir el archivo
     $spreadsheet->getActiveSheet()->setSelectedCell('A2');
     $ingredientsSheet->setSelectedCell('A2');
     // 16. Generar el archivo
     $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
     $writer->setPreCalculateFormulas(true); // Calcular fórmulas antes de guardar
     
     // Crear archivo temporal y enviarlo correctamente
     $filename = ($type === 'sub') ? 'Plantilla para importar Subrecetas.xlsx' : 'Plantilla para importar Recetas.xlsx';
     $tempFile = tempnam(sys_get_temp_dir(), $filename);
     $writer->save($tempFile);

     return Yii::$app->response->sendFile($tempFile, $filename);
 }
public function actionEditStep()
{
    $id = Yii::$app->request->post('id');
    $step = RecipeStep::findOne($id);

    if ($step) {
        $step->activity = Yii::$app->request->post('activity');
        $timeNa = Yii::$app->request->post('time_na');
        $step->time = ($timeNa === '1') ? null : Yii::$app->request->post('time');
        $step->indicator = Yii::$app->request->post('indicator');
        $removeImage = Yii::$app->request->post('remove_image');
        $step->_image = UploadedFile::getInstanceByName('_image');
        
        if ($removeImage === '1') {
            $step->removeImages();
        }

        if ($step->save()) {
            return $this->asJson(['success' => true]);
        }
    }

    return $this->asJson(['success' => false]);
}

public function actionRenderSteps($id)
{
    $model = $this->findModel($id);
    $html = $this->renderPartial('create/_steps', ['model' => $model, 'contentOnly' => true]);
    return $this->asJson(['html' => $html]);
}

public function actionRenderSpecialSteps($id)
{
    $model = $this->findModel($id);
    $html = $this->renderPartial('create/_special_steps', ['model' => $model, 'contentOnly' => true]);
    return $this->asJson(['html' => $html]);
}

public function actionIngredientList($q = null, $recipeId = null)
{
    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    $business = \backend\helpers\RedisKeys::getBusiness();

    $query = (new \yii\db\Query())
        ->select(["id", "CONCAT(key, ' - ', ingredient, ' (', portion_um, ')') as text"])
        ->from('ingredient_stock')
        ->where(['business_id' => $business->id]);

    if (!empty($q)) {
        $query->andWhere(['like', 'ingredient', $q]);
    }

    if (!empty($recipeId)) {
        $query->andWhere(['not exists', (new \yii\db\Query())
            ->from('ingredient_standard_recipe')
            ->where('ingredient_id = ingredient_stock.id')
            ->andWhere(['standard_recipe_id' => $recipeId])
        ]);
    }

    $results = $query->limit(20)->all();

    return ['results' => $results];
}

public function actionRenderIngredients($id)
{
    $model = $this->findModel($id);
    $html = $this->renderPartial('create/_ingredients_table', ['model' => $model]);
    return $this->asJson(['html' => $html]);
}

    /**
     * Importa ventas desde un archivo Excel con formato ABC de Ventas
     * Extrae información de la columna Descripción (nombre del producto) y Unidades (cantidad vendida)
     * Además extrae la fecha desde la fila 1 del Excel: "ABC de Ventas Del 01/01/2022 al 01/02/2022"
     */
    public function actionImportSalesExcel()
    {
        if (Yii::$app->request->isPost) {
            $uploadedFile = \yii\web\UploadedFile::getInstanceByName('sales_file');

            if ($uploadedFile) {
                try {
                    $business = \backend\helpers\RedisKeys::getBusiness();

                    // Cargar el archivo Excel
                    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                    $spreadsheet = $reader->load($uploadedFile->tempName);
                    $sheet = $spreadsheet->getActiveSheet();


                    // Leer mes y año de las celdas B1 y B2
                    $monthCell = trim($sheet->getCell('B1')->getValue());
                    $year = (int)$sheet->getCell('B2')->getValue();

                    // Permitir tanto número como nombre de mes
                    $monthsMap = [
                        'enero' => 1,
                        'febrero' => 2,
                        'marzo' => 3,
                        'abril' => 4,
                        'mayo' => 5,
                        'junio' => 6,
                        'julio' => 7,
                        'agosto' => 8,
                        'septiembre' => 9,
                        'setiembre' => 9,
                        'octubre' => 10,
                        'noviembre' => 11,
                        'diciembre' => 12
                    ];
                    if (is_numeric($monthCell)) {
                        $month = (int)$monthCell;
                    } else {
                        $monthLower = mb_strtolower($monthCell, 'UTF-8');
                        $month = isset($monthsMap[$monthLower]) ? $monthsMap[$monthLower] : 0;
                    }

                    // Validar mes y año
                    if ($month < 1 || $month > 12) {
                        throw new \Exception('El mes debe estar entre 1 y 12 o ser un nombre de mes válido');
                    }
                    if ($year < 2000 || $year > 2100) {
                        throw new \Exception('El año debe estar entre 2000 y 2100');
                    }

                    $importedCount = 0;
                    $errors = [];
                    $warnings = [];

                    // Leer datos desde la fila 10 en adelante
                    $row = 10;
                    while (true) {
                        $description = trim($sheet->getCell("A$row")->getValue());
                        $sales = $sheet->getCell("B$row")->getValue();

                        // Si no hay descripción, terminar el procesamiento
                        if (empty($description)) {
                            break;
                        }

                        // Validar que las ventas sean un número válido
                        if (!is_numeric($sales) || $sales < 0) {
                            $errors[] = "Fila $row: Las ventas deben ser un número positivo";
                            $row++;
                            continue;
                        }

                        // Normalizar el nombre: eliminar espacios extra, dobles y al inicio/final
                        $normalizedDescription = preg_replace('/\s+/', ' ', trim($description));

                        // Buscar la receta o combo por descripción normalizada (coincidencia exacta)
                        $recipe = StandardRecipe::find()
                            ->where([
                                'business_id' => $business->id,
                                'title' => $normalizedDescription,
                                'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN,
                                'in_construction' => 0
                            ])
                            ->one();

                        $usedFuzzySearch = false;
                        // Si no se encuentra con coincidencia exacta, buscar con tolerancia a espacios extra
                        if (!$recipe) {
                            $recipe = StandardRecipe::find()
                                ->where(['business_id' => $business->id, 'type' => StandardRecipe::STANDARD_RECIPE_TYPE_MAIN, 'in_construction' => 0])
                                ->andWhere(['like', 'REPLACE(REPLACE(TRIM(title), "  ", " "), "  ", " ")', $normalizedDescription])
                                ->one();
                            if ($recipe) {
                                $usedFuzzySearch = true;
                            }
                        }

                        $combo = null;
                        if (!$recipe) {
                            // Buscar combo con coincidencia exacta
                            $combo = \common\models\Menu::find()
                                ->where([
                                    'business_id' => $business->id,
                                    'name' => $normalizedDescription
                                ])
                                ->one();
                            
                            // Si no se encuentra con coincidencia exacta, buscar con tolerancia a espacios extra
                            if (!$combo) {
                                $combo = \common\models\Menu::find()
                                    ->where(['business_id' => $business->id])
                                    ->andWhere(['like', 'REPLACE(REPLACE(TRIM(name), "  ", " "), "  ", " ")', $normalizedDescription])
                                    ->one();
                                if ($combo) {
                                    $usedFuzzySearch = true;
                                }
                            }
                        }

                        if ($recipe) {
                            // Generar advertencia si se encontró usando búsqueda tolerante a espacios
                            if ($usedFuzzySearch) {
                                $warnings[] = "Fila $row: Se encontró la receta '$description' con coincidencia aproximada. Verifique que el nombre esté correctamente escrito.";
                            }
                            
                            // Usar el método estático saveSales para guardar las ventas de la receta
                            if (\common\models\MonthlySales::saveSales(
                                \common\models\MonthlySales::TYPE_RECIPE,
                                $recipe->id,
                                $month,
                                $year,
                                $sales
                            )) {
                                $importedCount++;
                            } else {
                                $errors[] = "Fila $row: Error al guardar ventas para la receta '$description'";
                            }

                        } elseif ($combo) {
                            // Generar advertencia si se encontró usando búsqueda tolerante a espacios
                            if ($usedFuzzySearch) {
                                $warnings[] = "Fila $row: Se encontró el combo '$description' con coincidencia aproximada. Verifique que el nombre esté correctamente escrito.";
                            }
                            
                            // Usar el método estático saveSales para guardar las ventas del combo
                            if (\common\models\MonthlySales::saveSales(
                                \common\models\MonthlySales::TYPE_MENU,
                                $combo->id,
                                $month,
                                $year,
                                $sales
                            )) {
                                $importedCount++;
                            } else {
                                $errors[] = "Fila $row: Error al guardar ventas para el combo '$description'";
                            }

                        } else {
                            $errors[] = "Fila $row: No se encontró receta o combo con el nombre '$description'. Verifique que el nombre coincida exactamente con los registros existentes.";
                        }

                        $row++;
                    }
                    
                    // Preparar mensaje de resultado
                    $message = "Importación completada. $importedCount registros importados.";
                    $hasWarnings = !empty($warnings);
                    $hasErrors = !empty($errors);
                    
                    // Generar HTML para el modal de forma más simple
                    $html = '';
                    
                    // Resumen principal
                    $alertType = $hasErrors ? 'alert-warning' : 'alert-success';
                    $iconClass = $hasErrors ? 'fas fa-exclamation-triangle text-warning' : 'fas fa-check-circle text-success';
                    
                    $html .= '<div class="alert ' . $alertType . ' d-flex align-items-center mb-3">';
                    $html .= '<i class="' . $iconClass . ' me-3 fs-4"></i>';
                    $html .= '<div>';
                    $html .= '<h6 class="mb-1 fw-bold">Resultado de la Importación</h6>';
                    $html .= '<p class="mb-0">' . Html::encode($message) . '</p>';
                    $html .= '</div>';
                    $html .= '</div>';
                    
                    // Mostrar advertencias si las hay
                    /*if ($hasWarnings) {
                        $html .= '<div class="alert alert-info mb-3">';
                        $html .= '<h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Advertencias (' . count($warnings) . ' encontradas - datos procesados correctamente)</h6>';
                        $html .= '<div class="alert-content border rounded p-2" style="max-height: 200px; overflow-y: auto; background-color: #f8f9fa;">';
                        foreach ($warnings as $index => $warning) {
                            $html .= '<div class="text-muted small mb-1"><strong>' . ($index + 1) . '.</strong> ' . Html::encode($warning) . '</div>';
                        }
                        $html .= '</div>';
                        $html .= '</div>';
                    }*/
                    
                    // Mostrar errores si los hay
                    if ($hasErrors) {
                        $html .= '<div class="alert alert-danger mb-3">';
                        $html .= '<h6 class="mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Errores encontrados (' . count($errors) . ' registros no procesados)</h6>';
                        $html .= '<div class="alert-content border rounded p-2" style="max-height: 200px; overflow-y: auto; background-color: #f8f9fa;">';
                        foreach ($errors as $index => $error) {
                            $html .= '<div class="text-muted small mb-1"><strong>' . ($index + 1) . '.</strong> ' . Html::encode($error) . '</div>';
                        }
                        $html .= '</div>';
                        $html .= '</div>';
                    }
                    
                    // Retornar el HTML directamente para AJAX
                    return $html;

                } catch (\Exception $e) {
                    // Generar HTML de error para el modal
                    $html = '<div class="alert alert-danger d-flex align-items-center mb-3">';
                    $html .= '<i class="fas fa-exclamation-triangle text-danger me-3 fs-4"></i>';
                    $html .= '<div>';
                    $html .= '<h6 class="mb-1 fw-bold">Error al procesar el archivo</h6>';
                    $html .= '<p class="mb-0">' . Html::encode($e->getMessage()) . '</p>';
                    $html .= '</div>';
                    $html .= '</div>';
                    
                    return $html;
                }
            } else {
                // Error: no se seleccionó archivo
                $html = '<div class="alert alert-danger d-flex align-items-center mb-3">';
                $html .= '<i class="fas fa-exclamation-triangle text-danger me-3 fs-4"></i>';
                $html .= '<div>';
                $html .= '<h6 class="mb-1 fw-bold">Error</h6>';
                $html .= '<p class="mb-0">No se seleccionó ningún archivo</p>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            }
        }

        // Si no es una solicitud POST, redirigir a la vista de ventas
        return $this->redirect(['sales']);
    }

    public function actionDownloadSalesTemplate()
    {

    // Crear nuevo libro de Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Plantilla Ventas');

    // Quitar líneas de cuadrícula
    $sheet->setShowGridlines(false);

    // Configurar encabezados de información
    $sheet->setCellValue('A1', 'MES:');
    $sheet->setCellValue('B1', '');
    $sheet->setCellValue('A2', 'AÑO:');
    $sheet->setCellValue('B2', '');

        // Agregar bordes a MES y AÑO y sus casillas
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];
        $sheet->getStyle('A1:B1')->applyFromArray($borderStyle);
        $sheet->getStyle('A2:B2')->applyFromArray($borderStyle);

        // Agregar validación de datos tipo lista para la celda B1 (mes)
        $validation = $sheet->getCell('B1')->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Mes inválido');
        $validation->setError('Seleccione un mes de la lista');
        $validation->setPromptTitle('Seleccionar mes');
        $validation->setPrompt('Seleccione el mes de la lista desplegable');
        $validation->setFormula1('"Enero,Febrero,Marzo,Abril,Mayo,Junio,Julio,Agosto,Septiembre,Octubre,Noviembre,Diciembre"');

        // Agregar validación de datos tipo lista para la celda B2 (año)
        $currentYear = (int)date('Y');
        $years = range($currentYear - 5, $currentYear + 5);
        $yearsList = implode(',', $years);
        $yearValidation = $sheet->getCell('B2')->getDataValidation();
        $yearValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $yearValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $yearValidation->setAllowBlank(false);
        $yearValidation->setShowInputMessage(true);
        $yearValidation->setShowErrorMessage(true);
        $yearValidation->setShowDropDown(true);
        $yearValidation->setErrorTitle('Año inválido');
        $yearValidation->setError('Seleccione un año de la lista');
        $yearValidation->setPromptTitle('Seleccionar año');
        $yearValidation->setPrompt('Seleccione el año de la lista desplegable');
        $yearValidation->setFormula1('"' . $yearsList . '"');

        // Obtener recetas y combos
        $business = \backend\helpers\RedisKeys::getBusiness();
        $recipes = \common\models\StandardRecipe::find()
            ->where(['business_id' => $business->id, 'type' => \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_MAIN, 'in_construction' => 0])
            ->select('title')
            ->orderBy('title')
            ->asArray()->all();
        $combos = \common\models\Menu::find()
            ->where(['business_id' => $business->id])
            ->select('name')
            ->orderBy('name')
            ->asArray()->all();

        $names = [];
        foreach ($recipes as $r) {
            $names[] = $r['title'];
        }
        foreach ($combos as $c) {
            $names[] = $c['name'];
        }

        // Configurar encabezados de datos
        $sheet->setCellValue('A4', 'INSTRUCCIONES:');
        $sheet->setCellValue('A5', '1. Seleccione el mes y el año de la lista desplegable en las celdas B1 y B2');
        $sheet->setCellValue('A6', '2. Complete los datos de ventas en las columnas de abajo');
        $sheet->setCellValue('A7', '3. Guarde el archivo y súbalo al sistema');

    $sheet->setCellValue('A9', 'DESCRIPCIÓN');
    $sheet->setCellValue('B9', 'VENTAS');

    // Ajustar anchos de columna
    $sheet->getColumnDimension('A')->setWidth(50);
    $sheet->getColumnDimension('B')->setWidth(15);

    // Bordes para encabezados de tabla
    $sheet->getStyle('A9:B9')->applyFromArray($borderStyle);

        // Aplicar estilo a los encabezados
        $sheet->getStyle('A1:B2')->getFont()->setBold(true);
        $sheet->getStyle('A4')->getFont()->setBold(true);
        $sheet->getStyle('A9:B9')->getFont()->setBold(true);
        $sheet->getStyle('A9:B9')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC');

        // Rellenar la tabla con los nombres de recetas y combos
        $startRow = 10;
        $lastRow = $startRow + count($names) - 1;
        foreach ($names as $idx => $name) {
            $row = $startRow + $idx;
            $sheet->setCellValue('A' . $row, $name);
            // Validación para que solo se puedan ingresar números positivos en ventas
            $salesValidation = $sheet->getCell('B' . $row)->getDataValidation();
            $salesValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_WHOLE);
            $salesValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $salesValidation->setAllowBlank(true);
            $salesValidation->setShowInputMessage(true);
            $salesValidation->setShowErrorMessage(true);
            $salesValidation->setErrorTitle('Valor inválido');
            $salesValidation->setError('Ingrese un número entero mayor o igual a cero');
            $salesValidation->setPromptTitle('Cantidad de ventas');
            $salesValidation->setPrompt('Ingrese la cantidad de ventas para este producto');
            $salesValidation->setFormula1(0);
        }
        // Bordes simples a todas las filas de datos (DESCRIPCIÓN y VENTAS)
        if ($lastRow >= $startRow) {
            $sheet->getStyle('A'.$startRow.':B'.$lastRow)->applyFromArray($borderStyle);
        }

        // Crear el archivo en temporal y enviarlo
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'Plantilla_Importar_Ventas_' . date('Y-m-d_H-i-s') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($tempFile);

        return Yii::$app->response->sendFile($tempFile, $filename);
    }

    public function actionExportTheoreticalYieldExcel()
    {
        $business = RedisKeys::getBusiness();

        // Obtener los mismos datos que la vista
        $theoreticalYieldData = $business->getTheoreticalYield();

        // Preparar datos ligeros igual que en actionTheoreticalYield
        $preparedData = [];
        $allRecipeIds = [];
        foreach ($theoreticalYieldData['data'] as $cat) {
            foreach ($cat['recipes'] as $r) {
                if (!empty($r->id)) $allRecipeIds[] = $r->id;
            }
        }

        $recipesMap = [];
        $recipesByCategory = [];
        $combosByCategory = [];
        $categoryNames = array_map(function($c){ return $c['category']->name; }, $theoreticalYieldData['data']);

        if (!empty($allRecipeIds)) {
            $loaded = \common\models\StandardRecipe::find()
                ->where(['id' => $allRecipeIds])
                ->with(['ingredientRelations.ingredient', 'convoy'])
                ->indexBy('id')
                ->all();
            foreach ($loaded as $id => $model) {
                $recipesMap[$id] = $model;
            }
        } else {
            if (!empty($categoryNames)) {
                $loaded = \common\models\StandardRecipe::find()
                    ->where([
                        'business_id' => $business->id,
                        'in_construction' => 0,
                        'type' => \common\models\StandardRecipe::STANDARD_RECIPE_TYPE_MAIN,
                        'in_menu' => true,
                    ])
                    ->andWhere(['in', 'type_of_recipe', $categoryNames])
                    ->with(['ingredientRelations.ingredient', 'convoy'])
                    ->all();
                foreach ($loaded as $model) {
                    $recipesMap[$model->id] = $model;
                    $recipesByCategory[$model->type_of_recipe][] = $model;
                }
            }

            $loadedCombos = \common\models\Menu::find()
                ->where(['business_id' => $business->id, 'in_menu' => true])
                ->with(['category'])
                ->all();
            foreach ($loadedCombos as $menu) {
                $catName = $menu->category->name ?? null;
                if ($catName) $combosByCategory[$catName][] = $menu;
            }
        }

        foreach ($theoreticalYieldData['data'] as $cat) {
            $c = [
                'category' => $cat['category'],
                'recipes' => [],
                'combos' => [],
            ];

            $sourceRecipes = !empty($cat['recipes']) ? $cat['recipes'] : ($recipesByCategory[$cat['category']->name] ?? []);
            foreach ($sourceRecipes as $recipe) {
                $id = is_object($recipe) && isset($recipe->id) ? $recipe->id : null;
                $model = ($id && isset($recipesMap[$id])) ? $recipesMap[$id] : $recipe;
                $obj = new \stdClass();
                $obj->id = $model->id;
                $obj->title = $model->title;
                $obj->recipeLastPrice = $model->recipeLastPrice;
                $obj->price = $model->price;
                $obj->costPercent = $model->getCostPercent();
                $obj->is_food = $model->is_food;
                $c['recipes'][] = $obj;
            }

            $sourceCombos = !empty($cat['combos']) ? $cat['combos'] : ($combosByCategory[$cat['category']->name] ?? []);
            foreach ($sourceCombos as $combo) {
                $obj = new \stdClass();
                $obj->id = $combo->id;
                $obj->title = $combo->title ?? $combo->name;
                $obj->cost = $combo->total_cost ?? $combo->totalCostByLastPrice ?? $combo->total_cost_last_price;
                $obj->total_price = $combo->total_price ?? $combo->price ?? (method_exists($combo, 'getPrice') ? $combo->getPrice() : null);
                $obj->costPercent = method_exists($combo, 'getCostPercent') ? $combo->getCostPercent() : ($combo->cost_percent_last_price ?? 0);
                $c['combos'][] = $obj;
            }

            $preparedData[] = $c;
        }

        // Crear el archivo Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rentabilidad Teórica');

        // Configurar estilos
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4CAF50'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];

        $categoryStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2196F3'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];

        $dataStyle = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];

        // Configurar anchos de columna
        $sheet->getColumnDimension('A')->setWidth(40); // Nombre
        $sheet->getColumnDimension('B')->setWidth(15); // Costo
        $sheet->getColumnDimension('C')->setWidth(15); // Precio
        $sheet->getColumnDimension('D')->setWidth(20); // Costo %
        $sheet->getColumnDimension('E')->setWidth(15); // Tipo

        // Alinear columna A (nombres) a la izquierda
        $sheet->getStyle('A:A')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        // Agregar información general
        $sheet->setCellValue('A1', 'Rentabilidad Teórica');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        if ($theoreticalYieldData['theoricalTotal'] !== null) {
            $totalValue = is_numeric($theoreticalYieldData['theoricalTotal']) 
                ? (float)$theoreticalYieldData['theoricalTotal'] 
                : (float)str_replace(['%', ','], ['', '.'], $theoreticalYieldData['theoricalTotal']);
            $sheet->setCellValue('A2', 'Rentabilidad Total: ' . number_format($totalValue, 2) . '%');
            $sheet->mergeCells('A2:E2');
            $sheet->getStyle('A2')->getFont()->setBold(true);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        // Agregar fecha
        $sheet->setCellValue('A3', 'Fecha de generación: ' . date('d/m/Y'));
        $sheet->mergeCells('A3:E3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Headers de la tabla
        $sheet->setCellValue('A5', 'Nombre');
        $sheet->setCellValue('B5', 'Costo');
        $sheet->setCellValue('C5', 'Precio');
        $sheet->setCellValue('D5', 'Costo %');
        $sheet->setCellValue('E5', 'Tipo');

        $sheet->getStyle('A5:E5')->applyFromArray($headerStyle);

        $row = 6; // Comenzar después de los headers

        foreach ($preparedData as $category) {
            if (empty($category['recipes']) && empty($category['combos'])) continue;

            // Calcular promedio de costo porcentual para la categoría
            $totalCostPercent = 0;
            $totalItems = 0;

            foreach ($category['recipes'] as $recipe) {
                $costPercentValue = is_numeric($recipe->costPercent) 
                    ? (float)$recipe->costPercent 
                    : (float)str_replace(['%', ','], ['', '.'], $recipe->costPercent);
                $totalCostPercent += $costPercentValue;
                $totalItems++;
            }

            foreach ($category['combos'] as $combo) {
                $costPercentValue = is_numeric($combo->costPercent) 
                    ? (float)$combo->costPercent 
                    : (float)str_replace(['%', ','], ['', '.'], $combo->costPercent);
                $totalCostPercent += $costPercentValue;
                $totalItems++;
            }

            $averageCostPercent = $totalItems > 0 ? $totalCostPercent / $totalItems : 0;

            // Agregar fila de categoría
            $sheet->setCellValue('A'.$row, $category['category']->name . ': ' . number_format($averageCostPercent * 100, 2) . '%');
            $sheet->mergeCells('A'.$row.':E'.$row);
            $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($categoryStyle);
            $row++;

            // Agregar recetas
            foreach ($category['recipes'] as $recipe) {
                $costPercentValue = is_numeric($recipe->costPercent) 
                    ? (float)$recipe->costPercent 
                    : (float)str_replace(['%', ','], ['', '.'], $recipe->costPercent);
                $sheet->setCellValue('A'.$row, $recipe->title);
                $sheet->setCellValue('B'.$row, number_format((float)$recipe->recipeLastPrice, 2));
                $sheet->setCellValue('C'.$row, number_format((float)$recipe->price, 2));
                $sheet->setCellValue('D'.$row, number_format($costPercentValue * 100, 2) . '%');
                $sheet->setCellValue('E'.$row, $recipe->is_food ? 'Alimento' : 'Bebida');

                $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($dataStyle);
                $row++;
            }

            // Agregar combos
            foreach ($category['combos'] as $combo) {
                $costPercentValue = is_numeric($combo->costPercent) 
                    ? (float)$combo->costPercent 
                    : (float)str_replace(['%', ','], ['', '.'], $combo->costPercent);
                $sheet->setCellValue('A'.$row, $combo->title);
                $sheet->setCellValue('B'.$row, number_format((float)$combo->cost, 2));
                $sheet->setCellValue('C'.$row, number_format((float)$combo->total_price, 2));
                $sheet->setCellValue('D'.$row, number_format($costPercentValue * 100, 2) . '%');
                $sheet->setCellValue('E'.$row, 'Combo');

                $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($dataStyle);
                $row++;
            }

            // Agregar fila vacía entre categorías
            $row++;
        }

        // Formato de moneda para columnas B y C
        $lastRow = $row - 1;
        $sheet->getStyle('B6:B'.$lastRow)->getNumberFormat()->setFormatCode('$#,##0.00');
        $sheet->getStyle('C6:C'.$lastRow)->getNumberFormat()->setFormatCode('$#,##0.00');

        // Crear y enviar el archivo
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'Rentabilidad_Teorica_' . date('Y-m-d_H-i-s') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($tempFile);

        return Yii::$app->response->sendFile($tempFile, $filename);
    }
}
