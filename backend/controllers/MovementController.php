<?php

namespace backend\controllers;

use backend\helpers\ExcelHelper;
use backend\helpers\RedisKeys;
use common\models\Balance;
use common\models\Business;
use Da\User\Traits\ContainerAwareTrait;
use Da\User\Validator\AjaxRequestModelValidator;
use Yii;
use common\models\Movement;
use common\models\MovementSearch;
use yii\filters\AccessControl;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;

/**
 * MovementController implements the CRUD actions for Movement model.
 */
class MovementController extends Controller
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
                    'delete-by-id' => ['POST'],
                    'delete-by-fecha' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [                    [
                        'actions' => [
                            'index',
                            'view',
                            'download-template',
                            'export-movements',
                            'import-movements',
                            'balance',
                            'get-provider-payment-types',
                            'convert-to-entry',
                            'convert-to-output',
                            'check-stock-availability'
                        ],
                        'allow' => true,
                        'roles' => [
                            'movements_view',
                            'movements_list'
                        ],
                    ],
                    [
                        'actions' => [
                            'create',
                            'create-requisition',
                            'update-requisition',
                        ],
                        'allow' => true,
                        'roles' => [
                            'movements_view',
                            'movements_list'
                        ],
                    ],
                    [
                        'actions' => [
                            'update',
                        ],
                        'allow' => true,
                        'roles' => [
                            'manage_account',
                            'admin',
                            'administrator',
                        ],
                    ],
                    [
                        'actions' => [
                            'delete',
                            'delete-by-id',
                            'delete-by-fecha',
                        ],
                        'allow' => true,
                        'roles' => [
                            'manage_users',
                        ],
                    ],
                    [
                        'actions' => [
                            'get-last-input-price',
                        ],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'backupReminder' => [
                'class' => \backend\components\BackupReminderBehavior::class,
            ],
        ];
    }

    /**
     * Lists all Movement models.
     * @return mixed
     */
    public function actionIndex()
    {
        $business = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        
        // Determinar el tamaño de página
        $savedPageSize = (int)Yii::$app->request->cookies->getValue('movements-per-page', 10);
        $perPage = Yii::$app->request->get('per-page');
        
        if ($perPage !== null) {
            $pageSize = (int)$perPage;
            // Guardar en cookie para persistencia
            Yii::$app->response->cookies->add(new \yii\web\Cookie([
                'name' => 'movements-per-page',
                'value' => $pageSize,
                'expire' => time() + 86400 * 365, // 1 año
            ]));
        } else {
            $pageSize = $savedPageSize;
        }
        
        $searchModel = new MovementSearch(['business_id' => $business['id']]);

        $params = Yii::$app->request->queryParams;
        
        // Si el usuario tiene el rol consumption_requester, solo mostrar requisiciones
        if (Yii::$app->user->can('consumption_requester')) {
            // Forzar el filtro de tipo a requisiciones
            if (!isset($params['MovementSearch'])) {
                $params['MovementSearch'] = [];
            }
            $params['MovementSearch']['type'] = Movement::TYPE_REQUISITION;
        }
        
        $dataProvider = $searchModel->search($params);
        
        // Establecer el tamaño de página en el dataProvider
        $dataProvider->pagination->pageSize = $pageSize;
        
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Movement model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->renderAjax('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Movement model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($type)
    {
        // Si el tipo es REQUISITION, usar vista especial
        if ($type === Movement::TYPE_REQUISITION) {
            return $this->actionCreateRequisition();
        }
        
        Url::remember(['movement/create'], 'register-movement');
        $business = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        $model = new Movement([
            'business_id' => $business['id'],
            'type' => $type
        ]);
        $post = Yii::$app->request->post();

        // Precargar amount con el último precio de compra si es input y hay ingrediente seleccionado
        if ($type === Movement::TYPE_INPUT && empty($post) && empty($model->amount)) {
            $ingredientId = Yii::$app->request->get('ingredient_id');
            if ($ingredientId) {
                $lastInput = Movement::find()
                    ->where([
                        'type' => Movement::TYPE_INPUT,
                        'ingredient_id' => $ingredientId,
                        'business_id' => $business['id']
                    ])
                    ->andWhere(['not', ['amount' => null]])
                    ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
                    ->one();
                if ($lastInput) {
                    $model->amount = $lastInput->amount;
                }
            }
        }

        if (array_key_exists('ajax', $post)) {
            $this->make(AjaxRequestModelValidator::class, [$model])->validate();
            return;
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        } elseif ($model->hasErrors()) {
            // Mostrar errores específicos al usuario
            foreach ($model->getFirstErrors() as $field => $error) {
                Yii::$app->session->addFlash('error', ucfirst($field) . ': ' . $error);
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }
    /**
     * Devuelve el último precio de compra (amount) para un ingrediente y negocio (AJAX)
     */
    public function actionGetLastInputPrice($ingredientId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $business = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        $lastInput = \common\models\Movement::find()
            ->where([
                'type' => \common\models\Movement::TYPE_INPUT,
                'ingredient_id' => $ingredientId,
                'business_id' => $business['id']
            ])
            ->andWhere(['not', ['amount' => null]])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
            ->one();
        if ($lastInput) {
            return ['success' => true, 'amount' => $lastInput->amount];
        } else {
            return ['success' => false, 'amount' => null];
        }
    }
    
    /**
     * Crear requisición con múltiples insumos
     * @return mixed
     */
    public function actionCreateRequisition()
    {
        $business = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        $post = Yii::$app->request->post();
        
        if (Yii::$app->request->isPost) {
            $transaction = Yii::$app->db->beginTransaction();
            
            try {
                // Obtener datos del formulario
                $consumptionCenterId = $post['consumption_center_id'] ?? null;
                $requiredDate = $post['required_date'] ?? null;
                $observations = $post['observations'] ?? '';
                $clientTimezone = $post['client_timezone'] ?? 'UTC';
                $items = $post['items'] ?? [];
                $isExtemporaneous = ($post['is_extemporaneous'] ?? '0') === '1';
                $extemporaneousReason = $post['extemporaneous_reason_hidden'] ?? null;
                $urgency = $post['urgency'] ?? Movement::URGENCY_NORMAL;
                
                // Obtener fecha/hora actual del cliente (no del servidor)
                $clientCurrentDateTime = $post['client_current_datetime'] ?? date('Y-m-d H:i:s');
                
                // Validar que haya items
                if (empty($items)) {
                    throw new \Exception('Debe agregar al menos un insumo a la requisición');
                }
                
                // Validar centro de consumo
                if (empty($consumptionCenterId)) {
                    throw new \Exception('El centro de consumo es requerido');
                }
                
                // Obtener reglas de requisición del centro de consumo
                $centerRules = \common\models\ConsumptionCenterRequisitionRules::getForConsumptionCenter(
                    $consumptionCenterId,
                    $business['id']
                );
                
                // Validar si la requisición puede ser creada basándose en la FECHA REQUERIDA, no en la fecha actual
                // Parsear la fecha requerida
                $requiredDateTime = new \DateTime($requiredDate, new \DateTimeZone($clientTimezone));
                $requiredDay = (int)$requiredDateTime->format('w'); // 0 = Domingo, 1 = Lunes, ..., 6 = Sábado
                $requiredTime = $requiredDateTime->format('H:i');
                
                // Obtener días permitidos
                $allowedDays = $centerRules->getRequisitionAllowedDaysArray();
                
                // Verificar si el día está permitido
                $isDayAllowed = in_array($requiredDay, $allowedDays);
                
                // Obtener horario específico para ese día desde consumption_center_schedule
                // IMPORTANTE: Ahora puede haber MÚLTIPLES rangos de horarios para el mismo día
                $schedulesForDay = \common\models\ConsumptionCenterSchedule::findAll([
                    'consumption_center_id' => $consumptionCenterId,
                    'day_of_week' => $requiredDay,
                    'is_active' => true
                ]);
                
                // Verificar si la hora está dentro de ALGUNO de los rangos configurados
                $isTimeAllowed = false;
                
                if (!empty($schedulesForDay)) {
                    // Verificar cada rango de horario
                    foreach ($schedulesForDay as $schedule) {
                        if ($requiredTime >= $schedule->start_time && $requiredTime <= $schedule->end_time) {
                            $isTimeAllowed = true;
                            break; // Encontró un rango válido, no necesita seguir buscando
                        }
                    }
                } else {
                    // Fallback: si no hay horarios específicos para este día, usar horario global
                    $startTime = $centerRules->requisition_start_time ?: '00:00';
                    $endTime = $centerRules->requisition_end_time ?: '23:59';
                    $isTimeAllowed = $requiredTime >= $startTime && $requiredTime <= $endTime;
                }
                
                // Determinar si está fuera de tiempo basándose en la fecha requerida
                $isOutOfTime = !$isDayAllowed || !$isTimeAllowed;
                
                // Determinar el estado de tiempo de la requisición
                $timeStatus = \common\models\Movement::TIME_STATUS_ON_TIME;
                
                if ($isOutOfTime) {
                    // Si está fuera de tiempo, verificar si hay motivo
                    if (!empty($extemporaneousReason) && $centerRules->allow_extemporaneous_requisitions) {
                        // Tiene motivo Y se permiten extemporáneas = EXTEMPORÁNEA
                        $timeStatus = \common\models\Movement::TIME_STATUS_EXTEMPORANEOUS;
                        $isExtemporaneous = true;
                    } else {
                        // No tiene motivo O no se permiten extemporáneas = FUERA DE TIEMPO
                        $timeStatus = \common\models\Movement::TIME_STATUS_OUT_OF_TIME;
                        $isExtemporaneous = false;
                    }
                }
                
                // Crear el movimiento de requisición (sin afectar inventario)
                $movement = new Movement([
                    'business_id' => $business['id'],
                    'type' => Movement::TYPE_REQUISITION,
                    'consumption_center_id' => $consumptionCenterId,
                    'required_date' => $requiredDate,
                    'observations' => $observations,
                    'client_timezone' => $clientTimezone,
                    'requested_by_user_id' => Yii::$app->user->id,
                    'status' => 'pending',
                    'created_at' => $clientCurrentDateTime, // Usar fecha/hora del cliente
                    'is_extemporaneous' => $isExtemporaneous,
                    'extemporaneous_reason' => !empty($extemporaneousReason) ? $extemporaneousReason : null,
                    'requisition_time_status' => $timeStatus,
                    'urgency' => $urgency,
                ]);
                
                if (!$movement->save()) {
                    $errors = implode(', ', $movement->getFirstErrors());
                    Yii::error('Error al guardar requisición: ' . $errors, __METHOD__);
                    throw new \Exception('No se pudo guardar la requisición. Por favor, verifique los datos.');
                }
                
                // Crear los items de la requisición
                $savedCount = 0;
                foreach ($items as $item) {
                    if (empty($item['ingredient_id']) || empty($item['quantity'])) {
                        continue;
                    }
                    
                    // Normalizar la cantidad: agregar 0 inicial si empieza con punto
                    $quantity = $item['quantity'];
                    if (is_string($quantity) && strpos($quantity, '.') === 0) {
                        $quantity = '0' . $quantity;
                    }
                    
                    // Log temporal para debug
                    Yii::warning("Cantidad recibida: " . var_export($item['quantity'], true) . " (tipo: " . gettype($item['quantity']) . ")", __METHOD__);
                    Yii::warning("Cantidad normalizada: " . var_export($quantity, true), __METHOD__);
                    
                    $requisitionItem = new \common\models\RequisitionItem([
                        'requisition_id' => $movement->id,
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity_requested' => $quantity,
                    ]);
                    
                    // Log después de asignar
                    Yii::warning("Cantidad asignada al modelo: " . var_export($requisitionItem->quantity_requested, true), __METHOD__);
                    
                    if (!$requisitionItem->save()) {
                        $errors = implode(', ', $requisitionItem->getFirstErrors());
                        Yii::error('Error al guardar item de requisición: ' . $errors, __METHOD__);
                        throw new \Exception('Error al guardar uno de los insumos. Verifique las cantidades.');
                    }
                    
                    // Log después de guardar
                    Yii::warning("Cantidad guardada en BD: " . var_export($requisitionItem->quantity_requested, true), __METHOD__);
                    
                    $savedCount++;
                }
                
                if ($savedCount == 0) {
                    throw new \Exception('No se guardó ningún insumo válido');
                }
                
                $transaction->commit();
                
                Yii::$app->session->addFlash('success', 
                    "Requisición {$movement->requisition_number} creada exitosamente con {$savedCount} insumo(s).");
                return $this->redirect(['index']);
                
            } catch (\Exception $e) {
                $transaction->rollBack();
                
                // Registrar el error completo en el log para debugging
                Yii::error('Error al crear requisición: ' . $e->getMessage(), __METHOD__);
                
                // Mostrar mensaje genérico al usuario (sin detalles SQL por seguridad)
                $userMessage = 'Error al crear la requisición.';
                
                // Solo incluir mensaje técnico si NO contiene SQL (para evitar exponer estructura de BD)
                if (!preg_match('/SQL|INSERT|UPDATE|DELETE|SELECT|CREATE|DROP|ALTER/i', $e->getMessage())) {
                    $userMessage .= ' ' . $e->getMessage();
                } else {
                    $userMessage .= ' Por favor, revise los datos e intente nuevamente.';
                }
                
                Yii::$app->session->addFlash('error', $userMessage);
            }
        }
        
        return $this->render('create_requisition');
    }

    /**
     * Updates an existing Movement model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        $post = Yii::$app->request->post();

        if (array_key_exists('ajax', $post)) {
            $this->make(AjaxRequestModelValidator::class, [$model])->validate();
            return;
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        } elseif ($model->hasErrors()) {
            // Mostrar errores específicos al usuario
            foreach ($model->getFirstErrors() as $field => $error) {
                Yii::$app->session->addFlash('error', ucfirst($field) . ': ' . $error);
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Actualiza una requisición existente
     * Solo permite editar si está en estado "Pendiente"
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdateRequisition($id)
    {
        $model = $this->findModel($id);
        
        // Verificar que sea una requisición
        if ($model->type !== Movement::TYPE_REQUISITION) {
            Yii::$app->session->addFlash('error', 'Solo se pueden editar requisiciones con esta acción.');
            return $this->redirect(['index']);
        }
        
        // Verificar que esté en estado "Pendiente"
        if ($model->status !== 'pending') {
            Yii::$app->session->addFlash('error', 'Solo se pueden editar requisiciones en estado "Pendiente".');
            return $this->redirect(['index']);
        }
        
        $businessData = \backend\helpers\RedisKeys::getValue(\backend\helpers\RedisKeys::BUSINESS_KEY);
        $business = \common\models\Business::findOne(['id' => $businessData['id']]);
        
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            
            $transaction = Yii::$app->db->beginTransaction();
            
            try {
                // Actualizar datos básicos de la requisición
                $model->consumption_center_id = $post['consumption_center_id'];
                $model->required_date = $post['required_date'];
                $model->urgency = $post['urgency'] ?? Movement::URGENCY_NORMAL;
                $model->observations = $post['observations'] ?? '';
                $model->client_timezone = $post['client_timezone'] ?? 'UTC';
                
                // Manejar requisición extemporánea
                $isExtemporaneous = ($post['is_extemporaneous'] ?? '0') === '1';
                $extemporaneousReason = $post['extemporaneous_reason_hidden'] ?? '';
                
                if ($isExtemporaneous && !empty($extemporaneousReason)) {
                    $model->extemporaneous_reason = $extemporaneousReason;
                } else {
                    $model->extemporaneous_reason = null;
                }
                
                if (!$model->save()) {
                    $errorMessages = [];
                    foreach ($model->getFirstErrors() as $field => $error) {
                        $errorMessages[] = $error;
                    }
                    throw new \Exception('Error al actualizar la requisición: ' . implode('. ', $errorMessages));
                }
                
                // Eliminar items antiguos
                \common\models\RequisitionItem::deleteAll(['requisition_id' => $model->id]);
                
                // Guardar nuevos items
                $items = $post['items'] ?? [];
                foreach ($items as $item) {
                    if (empty($item['ingredient_id']) || empty($item['quantity'])) {
                        continue;
                    }
                    
                    $requisitionItem = new \common\models\RequisitionItem();
                    $requisitionItem->requisition_id = $model->id;
                    $requisitionItem->ingredient_id = $item['ingredient_id'];
                    $requisitionItem->quantity_requested = $item['quantity'];
                    $requisitionItem->quantity_fulfilled = 0;
                    
                    if (!$requisitionItem->save()) {
                        $itemErrors = implode('. ', $requisitionItem->getFirstErrors());
                        throw new \Exception('Error al guardar item: ' . $itemErrors);
                    }
                }
                
                $transaction->commit();
                
                Yii::$app->session->addFlash('success', 'La requisición ha sido actualizada exitosamente.');
                return $this->redirect(['index']);
                
            } catch (\Exception $e) {
                $transaction->rollBack();
                Yii::error('Error al actualizar requisición: ' . $e->getMessage(), __METHOD__);
                
                $userMessage = 'Ocurrió un error al actualizar la requisición.';
                if (!preg_match('/SQL|INSERT|UPDATE|DELETE|SELECT|CREATE|DROP|ALTER/i', $e->getMessage())) {
                    $userMessage .= ' ' . $e->getMessage();
                } else {
                    $userMessage .= ' Por favor, revise los datos e intente nuevamente.';
                }
                
                Yii::$app->session->addFlash('error', $userMessage);
            }
        }
        
        // Cargar items existentes con la relación ingredient
        $existingItems = \common\models\RequisitionItem::find()
            ->where(['requisition_id' => $model->id])
            ->with('ingredient')
            ->all();
        
        // Log de depuración
        Yii::info('========== DEBUG UPDATE REQUISITION ==========', __METHOD__);
        Yii::info('Requisition ID: ' . $model->id, __METHOD__);
        Yii::info('Cantidad de items encontrados: ' . count($existingItems), __METHOD__);
        foreach ($existingItems as $index => $item) {
            Yii::info('Item #' . ($index + 1) . ':', __METHOD__);
            Yii::info('  - ID: ' . $item->id, __METHOD__);
            Yii::info('  - Ingredient ID: ' . $item->ingredient_id, __METHOD__);
            Yii::info('  - Quantity Requested: ' . $item->quantity_requested, __METHOD__);
            Yii::info('  - Ingredient loaded: ' . (isset($item->ingredient) ? 'YES' : 'NO'), __METHOD__);
            if (isset($item->ingredient)) {
                Yii::info('  - Ingredient Name: ' . $item->ingredient->ingredient, __METHOD__);
            }
        }
        Yii::info('Items array para JSON: ' . json_encode(array_map(function($item) {
            return [
                'ingredient_id' => $item->ingredient_id,
                'quantity' => $item->quantity_requested,
                'has_ingredient' => isset($item->ingredient),
                'ingredient_name' => isset($item->ingredient) ? $item->ingredient->ingredient : 'N/A',
            ];
        }, $existingItems)), __METHOD__);
        Yii::info('========== END DEBUG ==========', __METHOD__);
        
        return $this->render('update_requisition', [
            'model' => $model,
            'existingItems' => $existingItems,
        ]);
    }

    /**
     * Convierte una orden en una entrada
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionConvertToEntry($id)
    {
        $originalOrder = $this->findModel($id);
        
        // Verificar que sea una orden
        if ($originalOrder->type !== Movement::TYPE_ORDER) {
            Yii::$app->session->addFlash('error', 'Solo se pueden convertir órdenes en entradas.');
            return $this->redirect(['view', 'id' => $id]);
        }
        
        // Crear nuevo movimiento basado en la orden
        $newEntry = new Movement();
        $newEntry->type = Movement::TYPE_INPUT;
        $newEntry->business_id = $originalOrder->business_id;
        $newEntry->ingredient_id = $originalOrder->ingredient_id;
        $newEntry->provider = $originalOrder->provider;
        $newEntry->payment_type = $originalOrder->payment_type;
        $newEntry->invoice = $originalOrder->invoice;
        $newEntry->quantity = $originalOrder->quantity;
        $newEntry->um = $originalOrder->um;
        $newEntry->amount = $originalOrder->amount;
        $newEntry->tax = $originalOrder->tax;
        $newEntry->retention = $originalOrder->retention;
        $newEntry->unit_price = $originalOrder->unit_price;
        $newEntry->total = $originalOrder->total;
        $newEntry->observations = $originalOrder->observations . ' (Convertido de orden #' . $originalOrder->id . ')';
        $newEntry->created_at = date('Y-m-d H:i:s'); // Fecha actual para la nueva entrada
        
        if ($newEntry->save()) {
            Yii::$app->session->addFlash('success', 'La orden se ha convertido exitosamente en una entrada.');
            return $this->redirect(['index']);
        } else {
            // Mostrar errores específicos
            foreach ($newEntry->getFirstErrors() as $field => $error) {
                Yii::$app->session->addFlash('error', ucfirst($field) . ': ' . $error);
            }
            return $this->redirect(['view', 'id' => $id]);
        }
    }

    /**
     * Convierte una requisición en salidas (completa o parcialmente)
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionConvertToOutput($id)
    {
        $originalRequisition = $this->findModel($id);
        
        // Verificar que sea una requisición
        if ($originalRequisition->type !== Movement::TYPE_REQUISITION) {
            $message = 'Solo se pueden convertir requisiciones en salidas.';
            Yii::$app->session->addFlash('error', $message);
            
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['success' => false, 'message' => $message];
            }
            return $this->redirect(['index']);
        }
        
        // Verificar que la requisición tenga items
        $items = $originalRequisition->requisitionItems;
        if (empty($items)) {
            $message = 'La requisición no tiene insumos.';
            Yii::$app->session->addFlash('error', $message);
            
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['success' => false, 'message' => $message];
            }
            return $this->redirect(['index']);
        }
        
        // Si no es POST, redirigir a la vista
        if (!Yii::$app->request->isPost) {
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['success' => false, 'message' => 'Método no permitido'];
            }
            return $this->redirect(['view', 'id' => $id]);
        }
        
        $post = Yii::$app->request->post();
        $fulfillQuantities = $post['fulfill_quantities'] ?? [];
        
        if (empty($fulfillQuantities)) {
            $message = 'Debe especificar al menos una cantidad a surtir.';
            Yii::$app->session->addFlash('error', $message);
            
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['success' => false, 'message' => $message];
            }
            return $this->redirect(['index']);
        }
        
        $transaction = Yii::$app->db->beginTransaction();
        
        try {
            $outputsCreated = 0;
            $insufficientStock = [];
            $partiallyFulfilled = [];
            
            // Procesar cada item con cantidad especificada
            foreach ($items as $item) {
                if (!isset($fulfillQuantities[$item->id])) {
                    continue;
                }
                
                // Normalizar el valor: reemplazar coma por punto si existe
                $quantityValue = $fulfillQuantities[$item->id];
                $quantityValue = str_replace(',', '.', $quantityValue);
                $quantityToFulfill = floatval($quantityValue);
                
                // Saltar si la cantidad es 0 o negativa
                if ($quantityToFulfill <= 0) {
                    continue;
                }
                
                $ingredient = $item->ingredient;
                
                if (!$ingredient) {
                    continue;
                }
                
                // Calcular saldo pendiente
                $alreadyFulfilled = $item->quantity_fulfilled ?? 0;
                $pendingQuantity = $item->quantity_requested - $alreadyFulfilled;
                
                // Permitir hasta 30% adicional del saldo pendiente
                $maxAllowed = $pendingQuantity * 1.30;
                
                // Validar que no exceda el máximo permitido (130%)
                if ($quantityToFulfill > $maxAllowed) {
                    $quantityToFulfill = $maxAllowed;
                }
                
                // Obtener cantidad disponible (solo para mostrar advertencias)
                $availableQuantity = $ingredient->quantity ?? 0;
                
                // Permitir crear salidas incluso con stock insuficiente (puede quedar negativo)
                $actualQuantityToFulfill = $quantityToFulfill;
                
                // Registrar si hubo stock insuficiente (solo informativo)
                if ($availableQuantity < $quantityToFulfill) {
                    $insufficientStock[] = [
                        'ingredient' => $ingredient->ingredient,
                        'requested' => $quantityToFulfill,
                        'available' => $availableQuantity,
                        'fulfilled' => $actualQuantityToFulfill,
                    ];
                }
                
                // Crear movimiento de salida
                $newOutput = new Movement();
                $newOutput->type = Movement::TYPE_OUTPUT;
                $newOutput->business_id = $originalRequisition->business_id;
                $newOutput->ingredient_id = $item->ingredient_id;
                $newOutput->consumption_center_id = $originalRequisition->consumption_center_id;
                $newOutput->quantity = $actualQuantityToFulfill;
                $newOutput->um = $ingredient->portion_um ?? $ingredient->um;
                
                // Construir observaciones
                $outputObservations = "Salida de requisición {$originalRequisition->requisition_number}";
                if ($availableQuantity < $quantityToFulfill) {
                    $outputObservations .= " (ADVERTENCIA: Stock insuficiente al momento de la salida. Disponible: {$availableQuantity})";
                }
                
                $newOutput->observations = $outputObservations;
                $newOutput->parent_requisition_id = $originalRequisition->id;
                $newOutput->created_at = date('Y-m-d H:i:s');
                
                if (!$newOutput->save()) {
                    $errors = implode(', ', $newOutput->getFirstErrors());
                    Yii::error("Error al crear salida para {$ingredient->ingredient}: {$errors}", __METHOD__);
                    throw new \Exception("Error al crear salida para {$ingredient->ingredient}.");
                }
                
                // Actualizar cantidad surtida en el item
                $newFulfilledQuantity = $alreadyFulfilled + $actualQuantityToFulfill;
                $item->quantity_fulfilled = $newFulfilledQuantity;
                
                // Agregar observaciones al item si aún falta por surtir
                if ($newFulfilledQuantity < $item->quantity_requested) {
                    $partiallyFulfilled[] = [
                        'ingredient' => $ingredient->ingredient,
                        'fulfilled' => $actualQuantityToFulfill,
                        'pending' => $item->quantity_requested - $newFulfilledQuantity,
                    ];
                }
                
                $item->save(false);
                
                $outputsCreated++;
            }
            
            // Actualizar estado de la requisición
            if ($outputsCreated > 0) {
                // Verificar si todos los items están completamente surtidos
                $allFulfilled = true;
                foreach ($items as $item) {
                    $pendingQuantity = $item->quantity_requested - ($item->quantity_fulfilled ?? 0);
                    if ($pendingQuantity > 0.01) {
                        $allFulfilled = false;
                        break;
                    }
                }
                
                if ($allFulfilled) {
                    $originalRequisition->status = 'fulfilled';
                } else {
                    $originalRequisition->status = 'partially_fulfilled';
                }
                
                $originalRequisition->fulfilled_by_user_id = Yii::$app->user->id;
                $originalRequisition->save(false);
            }
            
            $transaction->commit();
            
            // Mostrar resultados
            if ($outputsCreated > 0) {
                $message = "Se crearon {$outputsCreated} salida(s) exitosamente.";
                
                if ($originalRequisition->status === 'fulfilled') {
                    $message .= " La requisición está completamente surtida.";
                    Yii::$app->session->addFlash('success', $message);
                } else {
                    $message .= " La requisición está parcialmente surtida.";
                    Yii::$app->session->addFlash('warning', $message);
                }
            }
            
            // Mostrar detalles de items parcialmente surtidos
            if (!empty($partiallyFulfilled)) {
                $message = "Items con saldo pendiente:\n";
                foreach ($partiallyFulfilled as $pItem) {
                    $message .= "• {$pItem['ingredient']}: Surtido {$pItem['fulfilled']}, Pendiente {$pItem['pending']}\n";
                }
                Yii::$app->session->addFlash('info', $message);
            }
            
            // Mostrar información sobre stock insuficiente (advertencia, no error)
            if (!empty($insufficientStock)) {
                $message = "ADVERTENCIA: Algunos items tenían stock insuficiente (el inventario quedó negativo):\n";
                foreach ($insufficientStock as $stock) {
                    $message .= "• {$stock['ingredient']}: Solicitado {$stock['requested']}, " .
                               "Disponible {$stock['available']}, Surtido {$stock['fulfilled']}\n";
                }
                Yii::$app->session->addFlash('info', $message);
            }
            
            if ($outputsCreated == 0) {
                Yii::$app->session->addFlash('error', 'No se pudo crear ninguna salida. Verifique las cantidades ingresadas.');
            }
            
            // Si es una petición AJAX, devolver JSON
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['success' => true, 'message' => 'Operación completada exitosamente'];
            }
            
            return $this->redirect(['index']);
            
        } catch (\Exception $e) {
            $transaction->rollBack();
            
            // Registrar el error completo en el log
            Yii::error('Error al convertir requisición: ' . $e->getMessage(), __METHOD__);
            
            // Mostrar mensaje genérico sin exponer SQL
            $userMessage = 'Error al convertir la requisición.';
            if (!preg_match('/SQL|INSERT|UPDATE|DELETE|SELECT|CREATE|DROP|ALTER/i', $e->getMessage())) {
                $userMessage .= ' ' . $e->getMessage();
            } else {
                $userMessage .= ' Por favor, intente nuevamente.';
            }
            
            Yii::$app->session->addFlash('error', $userMessage);
            
            // Si es una petición AJAX, devolver JSON con error
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['success' => false, 'message' => $userMessage];
            }
            
            return $this->redirect(['index']);
        }
    }
    
    /**
     * Verifica la disponibilidad de stock para una requisición
     * @return mixed JSON response
     */
    public function actionCheckStockAvailability()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $ingredientId = Yii::$app->request->post('ingredient_id');
        $quantity = Yii::$app->request->post('quantity');
        
        if (empty($ingredientId) || empty($quantity)) {
            return [
                'success' => false,
                'message' => 'Parámetros inválidos'
            ];
        }
        
        $ingredient = \common\models\IngredientStock::findOne($ingredientId);
        
        if (!$ingredient) {
            return [
                'success' => false,
                'message' => 'Insumo no encontrado'
            ];
        }
        
        $availableQuantity = $ingredient->quantity ?? 0;
        $available = $availableQuantity >= $quantity;
        
        $message = '';
        if (!$available) {
            $message = "No hay suficiente stock disponible. Cantidad disponible: {$availableQuantity} {$ingredient->portion_um}, Cantidad solicitada: {$quantity} {$ingredient->portion_um}";
        }
        
        return [
            'success' => true,
            'available' => $available,
            'availableQuantity' => $availableQuantity,
            'requestedQuantity' => $quantity,
            'insufficientQuantity' => max(0, $quantity - $availableQuantity),
            'ingredientName' => $ingredient->ingredient,
            'um' => $ingredient->portion_um,
            'message' => $message
        ];
    }

    public function actionDownloadTemplate()
    {
        $businessData = \backend\helpers\RedisKeys::getValue(\backend\helpers\RedisKeys::BUSINESS_KEY);
        $businessId = $businessData['id'];
        ExcelHelper::generateMovementTemplate($businessId);
    }

    public function actionImportMovements()
    {
        $businessData = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        $business = Business::findOne(['id' => $businessData['id']]);

        $file = UploadedFile::getInstanceByName('movement-file');

        if ($file) {
            try {
                $result = ExcelHelper::importMovements($business, $file->tempName);

                $savedCount = $result['saved_count'] ?? 0;
                $errors = $result['errors'] ?? [];
                $errorCount = count($errors);
                $totalRows = $savedCount + $errorCount;

                if ($savedCount > 0) {
                    $successMsg = '<strong>' . Yii::t('app', 'Importación completada') . '</strong>';
                    $successMsg .= '<br>' . Yii::t('app', '{count} movimiento(s) importado(s) correctamente.', ['count' => $savedCount]);
                    if ($errorCount > 0) {
                        $successMsg .= ' ' . Yii::t('app', '{count} fila(s) no pudieron importarse (ver advertencia abajo).', ['count' => $errorCount]);
                    }
                    Yii::$app->session->setFlash('success', $successMsg);
                } elseif ($errorCount === 0) {
                    Yii::$app->session->setFlash('warning', Yii::t('app', 'El archivo no contenía movimientos para importar.'));
                }

                if (!empty($errors)) {
                    $warningMsg = '<strong>' . Yii::t('app', '{count} fila(s) no se importaron:', ['count' => $errorCount]) . '</strong><ul class="mb-0 mt-1">';
                    foreach ($errors as $err) {
                        $warningMsg .= '<li>' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</li>';
                    }
                    $warningMsg .= '</ul>';
                    Yii::$app->session->setFlash('warning', $warningMsg);
                }

            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', Yii::t('app', 'Error durante la importación: ') . $e->getMessage());
            }
        } else {
            Yii::$app->session->setFlash('error', Yii::t('app', 'No se seleccionó ningún archivo.'));
        }

        return $this->redirect(['movement/index']);
    }

    public function actionExportMovements()
    {
        $businessData = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
        $business = Business::findOne(['id' => $businessData['id']]);

        // Obtener los IDs seleccionados del parámetro GET
        $ids = Yii::$app->request->get('ids');
        
        // Si no hay IDs o si es 'all', exportar todos los movimientos del negocio
        if (empty($ids) || $ids === 'all') {
            ExcelHelper::exportMovements($business);
        } else {
            // Convertir los IDs de string a array (solo para selección específica)
            $idsArray = is_array($ids) ? $ids : explode(',', $ids);
            ExcelHelper::exportMovements($business, $idsArray);
        }
    }

    /**
     * Deletes an existing Movement model.
     * Solo rol manage_users puede borrar. Revierte stock para input/output.
     * Requisiciones y orders NO tocan stock (según requerimiento actual).
     * @return mixed
     */
    public function actionDelete()
    {
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['success' => false, 'message' => 'Solicitud inválida']);
        }

        if (!Yii::$app->user->can('manage_users')) {
            return $this->asJson(['success' => false, 'message' => 'No tienes permiso para eliminar movimientos. Se requiere rol manage_users.']);
        }

        $ids = Yii::$app->request->post('keys');

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($ids === 'all') {
                $businessData = RedisKeys::getValue(RedisKeys::BUSINESS_KEY);
                $business = Business::findOne(['id' => $businessData['id']]);
                if (!$business) {
                    throw new \Exception('Negocio no encontrado');
                }
                $movements = Movement::find()->where(['business_id' => $business->id])->all();
                foreach ($movements as $model) {
                    $this->revertStockForDelete($model);
                    if (!$model->delete()) {
                        throw new \Exception('No se pudo eliminar movimiento ID ' . $model->id);
                    }
                }
                $transaction->commit();
                return $this->asJson(['success' => true]);
            } elseif (!empty($ids) && is_array($ids)) {
                foreach ($ids as $id) {
                    $model = $this->findModel($id);
                    $this->revertStockForDelete($model);
                    if (!$model->delete()) {
                        throw new \Exception('No se pudo eliminar movimiento ID ' . $id);
                    }
                }
                $transaction->commit();
                return $this->asJson(['success' => true]);
            } else {
                $transaction->rollBack();
                return $this->asJson(['success' => false, 'message' => 'No se recibieron IDs para eliminar']);
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error('Error deleting movements: ' . $e->getMessage(), __METHOD__);
            $msg = preg_match('/SQL|INSERT|UPDATE|DELETE|SELECT/i', $e->getMessage()) ? 'Error al eliminar los movimientos. Intente nuevamente.' : $e->getMessage();
            return $this->asJson(['success' => false, 'message' => $msg]);
        }
    }

    /**
     * Deletes an existing Movement model by ID (single deletion).
     * Solo manage_users. Revierte stock si es input/output.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDeleteById($id)
    {
        if (!Yii::$app->user->can('manage_users')) {
            throw new \yii\web\ForbiddenHttpException('No tienes permiso para eliminar movimientos. Se requiere rol manage_users.');
        }

        $model = $this->findModel($id);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->revertStockForDelete($model);
            $model->delete();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error('Error deleting movement by id: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Error al eliminar el movimiento: ' . $e->getMessage());
            return $this->redirect(['index']);
        }

        return $this->redirect(['index']);
    }

    /**
     * Deletes existing Movement models by date.
     * Solo manage_users. Revierte stock para cada movimiento input/output.
     * @param string $fecha
     * @return mixed
     */
    public function actionDeleteByFecha($fecha)
    {
        if (!Yii::$app->user->can('manage_users')) {
            throw new \yii\web\ForbiddenHttpException('No tienes permiso para eliminar movimientos. Se requiere rol manage_users.');
        }

        $businessData = \backend\helpers\RedisKeys::getValue(\backend\helpers\RedisKeys::BUSINESS_KEY);
        $businessId = $businessData['id'] ?? null;
        
        $movements = Movement::find()->where(['fecha' => $fecha, 'business_id' => $businessId])->all();
        
        if (empty($movements)) {
            Yii::$app->session->setFlash('error', 'No se encontraron movimientos para la fecha especificada.');
            return $this->redirect(['index']);
        }
        
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $deletedCount = 0;
            foreach ($movements as $movement) {
                $this->revertStockForDelete($movement);
                if ($movement->delete() !== false) {
                    $deletedCount++;
                }
            }
            $transaction->commit();
            Yii::$app->session->setFlash('success', "Se eliminaron {$deletedCount} movimientos de la fecha {$fecha}.");
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error('Error deleting movements by fecha: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Error al eliminar movimientos: ' . $e->getMessage());
        }
        
        return $this->redirect(['index']);
    }

    /**
     * Revierte el efecto en ingredient_stock y stock_price al borrar un movimiento.
     * - INPUT: resta quantity * portions_per_unit y elimina el StockPrice asociado.
     * - OUTPUT: suma quantity (ya está en portion_um).
     * - REQUISITION / ORDER: no toca nada (requerimiento actual).
     * No afecta a otros insumos ni a otros precios.
     * @param Movement $model
     * @throws \Exception
     */
    private function revertStockForDelete(Movement $model)
    {
        // Solo input/output afectan inventario - requisiciones y orders se ignoran
        if (!in_array($model->type, [Movement::TYPE_INPUT, Movement::TYPE_OUTPUT], true)) {
            return;
        }

        if (empty($model->ingredient_id) || empty($model->quantity)) {
            return;
        }

        $ingredient = \common\models\IngredientStock::findOne($model->ingredient_id);
        if (!$ingredient) {
            Yii::warning("IngredientStock ID {$model->ingredient_id} no encontrado para revertir movimiento {$model->id}", __METHOD__);
            return;
        }

        if ($model->type === Movement::TYPE_INPUT) {
            $portionsPerUnit = (float)($ingredient->portions_per_unit ?: 1);
            $qtyInPortion = (float)$model->quantity * $portionsPerUnit;
            $ingredient->quantity = (float)$ingredient->quantity - $qtyInPortion;
            if (!$ingredient->save(false)) {
                throw new \Exception('No se pudo revertir el stock del insumo ' . $ingredient->ingredient);
            }
            $this->deleteStockPriceForInput($model, $ingredient);
        } elseif ($model->type === Movement::TYPE_OUTPUT) {
            $ingredient->quantity = (float)$ingredient->quantity + (float)$model->quantity;
            if (!$ingredient->save(false)) {
                throw new \Exception('No se pudo revertir el stock del insumo ' . $ingredient->ingredient);
            }
            // OUTPUT nunca toca stock_price
        }
    }

    /**
     * Elimina SOLO el StockPrice generado por el movimiento de entrada.
     * Evita borrar historial de otros precios. Si no encuentra coincidencia exacta, no borra nada.
     * @param Movement $model
     * @param \common\models\IngredientStock $ingredient
     */
    private function deleteStockPriceForInput(Movement $model, \common\models\IngredientStock $ingredient)
    {
        $dateStr = date('Y-m-d', strtotime($model->created_at));
        $unitPrice = $model->unit_price;

        // Intentar coincidencia exacta por unit_price + fecha
        $query = \common\models\StockPrice::find()
            ->where(['stock_id' => $ingredient->id])
            ->andWhere(['date' => $dateStr])
            ->orderBy(['id' => SORT_DESC]);

        // Filtrar por unit_price si existe (con tolerancia float)
        if ($unitPrice !== null && $unitPrice !== '') {
            $candidates = $query->all();
            $matched = null;
            foreach ($candidates as $sp) {
                if (abs((float)$sp->unit_price - (float)$unitPrice) < 0.0001) {
                    $matched = $sp;
                    break;
                }
                // fallback: comparar también por price si unit_price no coincide por redondeo
                if (abs((float)$sp->price - (float)$unitPrice) < 0.0001) {
                    $matched = $sp;
                    break;
                }
            }
            if ($matched) {
                $matched->delete();
                Yii::info("StockPrice ID {$matched->id} eliminado para revertir movimiento INPUT ID {$model->id}", __METHOD__);
                return;
            }
            // Si hay candidates pero ninguno matchea unit_price, buscar sin filtro de fecha
            $fallback = \common\models\StockPrice::find()
                ->where(['stock_id' => $ingredient->id])
                ->orderBy(['id' => SORT_DESC])
                ->all();
            foreach ($fallback as $sp) {
                if (abs((float)$sp->unit_price - (float)$unitPrice) < 0.0001 || abs((float)$sp->price - (float)$unitPrice) < 0.0001) {
                    // Solo borrar si es el más reciente con ese precio y fecha cercana
                    if ($sp->date === $dateStr) {
                        $sp->delete();
                        Yii::info("StockPrice fallback ID {$sp->id} eliminado para revertir movimiento INPUT ID {$model->id}", __METHOD__);
                    }
                    return;
                }
            }
            Yii::warning("No se encontró StockPrice coincidente para movimiento INPUT ID {$model->id} (unit_price={$unitPrice}, date={$dateStr}). No se borra ningún precio para no afectar historial.", __METHOD__);
            return;
        }

        // Si no hay unit_price en el movimiento, intentar borrar el último de esa fecha
        $lastOnDate = $query->one();
        if ($lastOnDate) {
            Yii::warning("Movimiento INPUT ID {$model->id} sin unit_price, no se elimina StockPrice automáticamente para evitar borrar historial. Revisar manualmente.", __METHOD__);
        }
    }

    public function actionBalance()
    {
        $business = RedisKeys::getBusinessData();

        $balances = Balance::find()->where(['business_id' => $business['id']])->orderBy(['date' => SORT_ASC])->all();

        return $this->renderAjax('balance', [
            'balances' => $balances
        ]);
    }

    public function actionRegisterBalance()
    {
        $business = RedisKeys::getBusinessData();

        $balance = new Balance([
            'business_id' => $business['id'],
            'created_by' => Yii::$app->user->id,
            'date' => date('Y-m-d')
        ]);

        $post = Yii::$app->request->post();

        if (array_key_exists('ajax', $post)) {
            $this->make(AjaxRequestModelValidator::class, [$balance])->validate();
        }

        if ($balance->load($post) && $balance->save()) {
            return $this->asJson(['success' => true]);
        } elseif ($balance->hasErrors()) {
            return $this->asJson(['success' => false, 'errors' => array_values(array_values($balance->errors))]);
        }        return $this->asJson(['success' => false, 'errors' => ["Algo falló"]]);
    }    /**
     * Obtiene los tipos de pago disponibles para un proveedor específico
     */
    public function actionGetProviderPaymentTypes($providerId = null)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        try {            // Si no se proporciona providerId, devolver todos los tipos de pago específicos
            if (empty($providerId)) {
                return [
                    'success' => true,
                    'paymentTypes' => \common\models\Movement::getFormattedPaymentMethods()
                ];
            }
            
            // Buscar el proveedor por ID
            $provider = \common\models\Provider::findOne($providerId);
              if (!$provider) {
                return [
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ];
            }
              // Obtener los métodos de pago del proveedor
            $providerPaymentMethods = $provider->payment_method;            if (empty($providerPaymentMethods)) {
                // Si el proveedor no tiene métodos de pago específicos, devolver todos
                return [
                    'success' => true,
                    'paymentTypes' => \common\models\Movement::getFormattedPaymentMethods()
                ];
            }
            
            // Asegurar que payment_method es un array
            if (!is_array($providerPaymentMethods)) {
                // Si es string, convertir a array
                if (is_string($providerPaymentMethods)) {
                    $providerPaymentMethods = explode(',', $providerPaymentMethods);
                    $providerPaymentMethods = array_map('trim', $providerPaymentMethods);                } else {
                    // Si no es array ni string, devolver todos los tipos específicos
                    return [
                        'success' => true,
                        'paymentTypes' => \common\models\Movement::getFormattedPaymentMethods()
                    ];
                }}            // Obtener los tipos de pago específicos del proveedor
            // En lugar de mapear a los tipos genéricos, mostrar los tipos específicos
            $providerPaymentTypes = \common\models\Movement::getFormattedPaymentMethods();
            
            // Filtrar solo los tipos de pago que acepta el proveedor
            $filteredPaymentTypes = [];
            
            foreach ($providerPaymentMethods as $method) {
                $method = trim($method); // Limpiar espacios
                
                if (isset($providerPaymentTypes[$method])) {
                    $filteredPaymentTypes[$method] = $providerPaymentTypes[$method];
                }
            }              // Si no se encontró ningún tipo de pago válido, devolver todos los tipos específicos
            if (empty($filteredPaymentTypes)) {
                return [
                    'success' => true,
                    'paymentTypes' => \common\models\Movement::getFormattedPaymentMethods()
                ];
            }
            
            return [
                'success' => true,
                'paymentTypes' => $filteredPaymentTypes
            ];
              } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener los tipos de pago: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Finds the Movement model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Movement the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Movement::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }
}
