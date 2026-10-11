<?php

/*
 * This file is part of the 2amigos/yii2-usuario project.
 *
 * (c) 2amigOS! <http://2amigos.us/>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace backend\controllers\user;

use backend\helpers\RedisKeys;
use backend\models\CreateUserForm;
use Codeception\Module\Redis;
use Da\User\Event\UserEvent;
use Da\User\Factory\MailFactory;
use Da\User\Filter\AccessRuleFilter;
use Da\User\Model\Profile;
use Da\User\Model\User;
use Da\User\Query\UserQuery;
use Da\User\Search\UserSearch;
use Da\User\Service\PasswordExpireService;
use Da\User\Service\PasswordRecoveryService;
use Da\User\Service\SwitchIdentityService;
use Da\User\Service\UserBlockService;
use Da\User\Service\UserConfirmationService;
use Da\User\Service\UserCreateService;
use Da\User\Traits\ContainerAwareTrait;
use Da\User\Traits\ModuleAwareTrait;
use Da\User\Validator\AjaxRequestModelValidator;
use Yii;
use yii\base\Module;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class AdminController extends Controller
{
    use ContainerAwareTrait;
    use ModuleAwareTrait;

    /**
     * @var UserQuery
     */
    protected $userQuery;

    /**
     * AdminController constructor.
     *
     * @param string $id
     * @param Module $module
     * @param UserQuery $userQuery
     * @param array $config
     */
    public function __construct($id, Module $module, UserQuery $userQuery, array $config = [])
    {
        $this->userQuery = $userQuery;
        parent::__construct($id, $module, $config);
    }

    /**
     * @param \yii\base\Action $action
     *
     * @return bool
     */
    public function beforeAction($action)
    {
        if (in_array($action->id, ['index', 'update', 'update-profile', 'info', 'assignments'], true)) {
            Url::remember('', 'actions-redirect');
        }

        return parent::beforeAction($action);
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'confirm' => ['post'],
                    'block' => ['post'],
                    'switch-identity' => ['post'],
                    'password-reset' => ['post'],
                    'force-password-change' => ['post'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'ruleConfig' => [
                    'class' => AccessRuleFilter::class,
                ],
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['switch-identity'],
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['role-permissions'],
                        'roles' => ['manage_users'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['admin'],
                    ],
                    [
                        'actions' => ['users', 'create-user', 'update-user', 'delete-user', 'delete'],
                        'allow' => true,
                        'roles' => ['manage_users']
                    ]
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = $this->make(UserSearch::class);
        $dataProvider = $searchModel->search(Yii::$app->request->get());

        return $this->render(
            'index',
            [
                'dataProvider' => $dataProvider,
                'searchModel' => $searchModel,
            ]
        );
    }

    public function actionCreate()
    {
        /** @var User $user */
        $user = $this->make(User::class, [], ['scenario' => 'create']);

        /** @var UserEvent $event */
        $event = $this->make(UserEvent::class, [$user]);

        $this->make(AjaxRequestModelValidator::class, [$user])->validate();

        if ($user->load(Yii::$app->request->post()) && $user->validate()) {
            $this->trigger(UserEvent::EVENT_BEFORE_CREATE, $event);

            $mailService = MailFactory::makeWelcomeMailerService($user);

            if ($this->make(UserCreateService::class, [$user, $mailService])->run()) {
                Yii::$app->getSession()->setFlash('success', Yii::t('usuario', 'User has been created'));
                $this->trigger(UserEvent::EVENT_AFTER_CREATE, $event);
                return $this->redirect(['update', 'id' => $user->id]);
            }
            Yii::$app->session->setFlash('danger', Yii::t('usuario', 'User account could not be created.'));
        }

        return $this->render('create', ['user' => $user]);
    }

    public function actionUpdate($id)
    {
        $user = $this->userQuery->where(['id' => $id])->one();
        $user->setScenario('update');
        /** @var UserEvent $event */
        $event = $this->make(UserEvent::class, [$user]);

        $this->make(AjaxRequestModelValidator::class, [$user])->validate();

        if ($user->load(Yii::$app->request->post())) {
            $this->trigger(UserEvent::EVENT_BEFORE_ACCOUNT_UPDATE, $event);

            if ($user->save()) {
                Yii::$app->getSession()->setFlash('success', Yii::t('usuario', 'Account details have been updated'));
                $this->trigger(UserEvent::EVENT_AFTER_ACCOUNT_UPDATE, $event);

                return $this->refresh();
            }
        }

        return $this->render('_account', ['user' => $user]);
    }

    public function actionUpdateProfile($id)
    {
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();
        /** @var Profile $profile */
        $profile = $user->profile;
        if ($profile === null) {
            $profile = $this->make(Profile::class);
            $profile->link('user', $user);
        }
        /** @var UserEvent $event */
        $event = $this->make(UserEvent::class, [$user]);

        $this->make(AjaxRequestModelValidator::class, [$profile])->validate();

        if ($profile->load(Yii::$app->request->post())) {
            if ($profile->save()) {
                $this->trigger(UserEvent::EVENT_BEFORE_PROFILE_UPDATE, $event);
                Yii::$app->getSession()->setFlash('success', Yii::t('usuario', 'Profile details have been updated'));
                $this->trigger(UserEvent::EVENT_AFTER_PROFILE_UPDATE, $event);

                return $this->refresh();
            }
        }

        return $this->render(
            '_profile',
            [
                'user' => $user,
                'profile' => $profile,
            ]
        );
    }

    public function actionInfo($id)
    {
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();

        return $this->render(
            '_info',
            [
                'user' => $user,
            ]
        );
    }

    public function actionAssignments($id)
    {
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();

        return $this->render(
            '_assignments',
            [
                'user' => $user,
                'params' => Yii::$app->request->post(),
            ]
        );
    }

    public function actionConfirm($id)
    {
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();
        /** @var UserEvent $event */
        $event = $this->make(UserEvent::class, [$user]);

        $this->trigger(UserEvent::EVENT_BEFORE_CONFIRMATION, $event);

        if ($this->make(UserConfirmationService::class, [$user])->run()) {
            Yii::$app->getSession()->setFlash('success', Yii::t('usuario', 'User has been confirmed'));
            $this->trigger(UserEvent::EVENT_AFTER_CONFIRMATION, $event);
        } else {
            Yii::$app->getSession()->setFlash(
                'warning',
                Yii::t('usuario', 'Unable to confirm user. Please, try again.')
            );
        }

        return $this->redirect(Url::previous('actions-redirect'));
    }

    public function actionDelete()
    {
//        if ((int)$id === Yii::$app->user->getId()) {
//            Yii::$app->getSession()->setFlash('danger', Yii::t('usuario', 'You cannot remove your own account'));
//        } else {
        $id = Yii::$app->user->identity->getId();
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();
        /** @var UserEvent $event */
        $event = $this->make(UserEvent::class, [$user]);
        $this->trigger(ActiveRecord::EVENT_BEFORE_DELETE, $event);

        if ($user->delete()) {
            Yii::$app->getSession()->setFlash('success', Yii::t('usuario', 'User has been deleted'));
            $this->trigger(ActiveRecord::EVENT_AFTER_DELETE, $event);
        } else {
            Yii::$app->getSession()->setFlash(
                'warning',
                Yii::t('usuario', 'Unable to delete user. Please, try again later.')
            );
        }


        return $this->redirect(['index']);
    }

    public function actionBlock($id)
    {
        if ((int)$id === Yii::$app->user->getId()) {
            Yii::$app->getSession()->setFlash('danger', Yii::t('usuario', 'You cannot remove your own account'));
        } else {
            /** @var User $user */
            $user = $this->userQuery->where(['id' => $id])->one();
            /** @var UserEvent $event */
            $event = $this->make(UserEvent::class, [$user]);

            if ($this->make(UserBlockService::class, [$user, $event, $this])->run()) {
                Yii::$app->getSession()->setFlash('success', Yii::t('usuario', 'User block status has been updated.'));
            } else {
                Yii::$app->getSession()->setFlash('danger', Yii::t('usuario', 'Unable to update block status.'));
            }
        }

        return $this->redirect(Url::previous('actions-redirect'));
    }

    public function actionSwitchIdentity($id = null)
    {
        if (false === $this->module->enableSwitchIdentities) {
            Yii::$app->getSession()->setFlash('danger', Yii::t('usuario', 'Switch identities is disabled.'));

            return $this->redirect(['index']);
        }

        $this->make(SwitchIdentityService::class, [$this, 2 => $id])->run();

        return $this->goHome();
    }

    public function actionPasswordReset($id)
    {
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();
        $mailService = MailFactory::makeRecoveryMailerService($user->email);
        if ($this->make(PasswordRecoveryService::class, [$user->email, $mailService])->run()) {
            Yii::$app->getSession()->setFlash('success', Yii::t('usuario', 'Recovery message sent'));
        } else {
            Yii::$app->getSession()->setFlash(
                'danger',
                Yii::t('usuario', 'Unable to send recovery message to the user')
            );
        }

        return $this->redirect(['index']);
    }

    /**
     * Forces the user to change password at next login
     * @param integer $id
     */
    public function actionForcePasswordChange($id)
    {
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();
        if ($this->make(PasswordExpireService::class, [$user])->run()) {
            Yii::$app->session->setFlash("success", Yii::t('usuario', 'User will be required to change password at next login'));
        } else {
            Yii::$app->session->setFlash("danger", Yii::t('usuario', 'There was an error in saving user'));
        }
        $this->redirect(['index']);
    }

    public function actionCreateUser()
    {
        $user = Yii::$app->user->identity;
        if ($user->hasRestrictions('users')) {
            Yii::$app->session->setFlash('warning', "Haz alcanzado el límite de usuarios");
            return $this->redirect(['//user/admin/users']);
        }
        $form = new CreateUserForm(['scenario' => CreateUserForm::SCENARIO_CREATE]);
        $post = Yii::$app->request->post();
        if (array_key_exists('ajax', $post)) {
            $this->make(AjaxRequestModelValidator::class, [$form])->validate();
        }

        $isSubmitted = $form->load($post);
        if ($isSubmitted && $form->save()) {
            Yii::$app->session->setFlash('success', 'Usuario creado exitosamente');
            return $this->redirect(['//user/admin/users']);
        }

        // Notificar al usuario cuando el envío falla (validación o error interno)
        if ($isSubmitted && !array_key_exists('ajax', $post)) {
            $firstError = $form->getFirstError();
            $msg = 'No se pudo crear el usuario.';
            if ($firstError) {
                $msg .= ' ' . $firstError;
            } else {
                $msg .= ' Revisa los campos e inténtalo de nuevo.';
            }
            Yii::$app->session->setFlash('danger', $msg);
        }

        return $this->render('create_user', [
            'model' => $form
        ]);
    }

    public function actionUpdateUser($id)
    {
        $user = User::findOne(['id' => $id]);
        if (empty($user)) {
            throw new NotFoundHttpException("Este usuario no existe");
        }


        // Obtener el rol actual del usuario
        $auth = \Yii::$app->authManager;
        $roles = $auth->getRolesByUser($user->id);
        $currentRole = count($roles) > 0 ? array_keys($roles)[0] : null;

        $form = new CreateUserForm([
            'email' => $user->email,
            'name' => $user->profile->name,
            'userId' => $user->id,
            'role' => $currentRole,
            'scenario' => CreateUserForm::SCENARIO_UPDATE
        ]);

        $post = Yii::$app->request->post();
        if (array_key_exists('ajax', $post)) {
            $this->make(AjaxRequestModelValidator::class, [$form])->validate();
        }

        if ($form->load($post) && $form->save()) {
            return $this->redirect(['//user/admin/users']);
        }

        return $this->render('update_user', [
            'model' => $form
        ]);
    }

    /**
     * Returns JSON list of permission names for a given role.
     * Used by AJAX in the user update form to preload permissions when role changes.
     * @param string $role
     * @return array
     */
    public function actionRolePermissions($role)
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $auth = \Yii::$app->authManager;
        if (empty($role)) {
            return [];
        }
        $perms = $auth->getPermissionsByRole($role);
        $permNames = [];
        foreach ($perms as $perm) {
            $permNames[] = is_object($perm) ? $perm->name : $perm;
        }
        return $permNames;
    }

    public function actionUsers()
    {
        $business = RedisKeys::getBusiness();
        $users = $business->getUsers()->all();

        return $this->render('users', [
            'users' => $users
        ]);
    }

    public function actionDeleteUser($id)
    {
        if (Yii::$app->request->isPost) {
            $user = User::findOne(['id' => $id]);
            if (!$user) {
                throw new NotFoundHttpException("Usuario no encontrado");
            }
            if ($user->id == Yii::$app->user->id) {
                Yii::$app->session->setFlash('danger', 'No puedes eliminar tu propio usuario.');
                return $this->redirect(['//user/admin/users']);
            }
            // Eliminar de user_business
            Yii::$app->db->createCommand()->delete('user_business', ['user_id' => $user->id])->execute();
            if ($user->delete()) {
                Yii::$app->session->setFlash('success', 'Usuario eliminado correctamente.');
            } else {
                Yii::$app->session->setFlash('danger', 'No se pudo eliminar el usuario.');
            }
            return $this->redirect(['//user/admin/users']);
        }
        throw new NotFoundHttpException("Método no permitido");
    }
}
