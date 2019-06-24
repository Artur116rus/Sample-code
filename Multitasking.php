<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Multitasking extends MY_Controller {

    public $task;

    /**
     * экземпляр класс TaskPermission из соответствующей библиотеки
     * @var
     */
    public $permissions;

    private function wrapperText($text){
        $text =  htmlspecialchars_decode(mb_substr(strip_tags($text), 0, 350));
        return $text;
    }

    public function __construct(){
        parent::__construct();
        $this->load->model('permission_model');
        $this->load->model('users_model');
        $this->load->model('employee_model');
        $this->load->model('multitasking_model');
        $this->load->model('modules_model');
        $this->load->model('agreement_model');

        $this->traits_library->redirectBanAgree(USER_COOKIE_ID);

        $this->load->library('TaskPermission');
        $this->permissions = $this->taskpermission;

        $rule1_ViewTask = new Rule('userIsKurator');
        $rule1_ViewTask->addAttribute( 'Subject', '\Entity\User', 'id', $this );
        $rule1_ViewTask->addAttribute( 'Subject', '\Entity\MacrotaskTask', 'kurator', $this );
        $rule1_ViewTask->addRelation('==');
        $this->rulesContainer->attach($rule1_ViewTask, 'userIsKurator');

        $rule2_ViewTask = new Rule('userIsIniciator');
        $rule2_ViewTask->addAttribute( 'Subject', '\Entity\User', 'id', $this );
        $rule2_ViewTask->addAttribute( 'Subject', '\Entity\MacrotaskTask', 'iniciator', $this );
        $rule2_ViewTask->addRelation('==');
        $this->rulesContainer->attach($rule2_ViewTask, 'userIsIniciator');

        $rule3_ViewTask = new Rule('userDepartmentIsOUKD');
        $rule3_ViewTask->addAttribute( 'Subject', '\Entity\User', 'department_id', $this );
        $rule3_ViewTask->addAttribute( 'Constant', '', DEPARTMENT_ID_3, null );
        $rule3_ViewTask->addRelation('==');
        $this->rulesContainer->attach($rule3_ViewTask, 'userDepartmentIsOUKD');

        $rule4_ViewTask = new Rule('userIsWorker');
        $rule4_ViewTask->addAttribute( 'Subject', '\Entity\User', 'id', $this );
        $rule4_ViewTask->addAttribute( 'Subject', '\Entity\MacrotaskTask', 'array_user_performers', $this, 'user_id' );
        $rule4_ViewTask->addRelation('in');
        $this->rulesContainer->attach($rule4_ViewTask, 'userIsWorker');

        $rule5_ViewTask = new Rule('userInWhiteList');
        $rule5_ViewTask->addAttribute( 'Subject', '\Entity\User', 'id', $this );
        $rule5_ViewTask->addAttribute( 'Constant', '', $this->getWhiteList(), null );
        $rule5_ViewTask->addRelation('in');
        $this->rulesContainer->attach($rule5_ViewTask, 'userInWhiteList');

        $rule6_ViewTask = new Rule('taskStatusIsCloseByUsers');
        $rule6_ViewTask->addAttribute( 'Subject', '\Entity\MacrotaskTask', 'status', $this );
        $rule6_ViewTask->addAttribute( 'Constant', '', 2, null );
        $rule6_ViewTask->addRelation('==');
        $this->rulesContainer->attach($rule6_ViewTask, 'taskStatusIsCloseByUsers');

        $rule7_ViewTask = new Rule('performersInTaskMoreThan1');
        $rule7_ViewTask->addAttribute( 'Subject', '\Entity\MacrotaskTask', 'count_performers', $this );
        $rule7_ViewTask->addAttribute( 'Constant', '', 1, null );
        $rule7_ViewTask->addRelation('>');
        $this->rulesContainer->attach($rule7_ViewTask, 'performersInTaskMoreThan1');

        $rule8_ViewTask = new Rule('performerNoLeaveTask');
        $rule8_ViewTask->addAttribute( 'Subject', '\Entity\User', 'id', $this );
        $rule8_ViewTask->addAttribute( 'Subject', '\Entity\MacrotaskTask', 'array_leaved_users', null );
        $rule8_ViewTask->addRelation('not in');
        $this->rulesContainer->attach($rule8_ViewTask, 'performerNoLeaveTask');

        $rule9_ViewTask = new Rule('taskStatusIsOpen');
        $rule9_ViewTask->addAttribute( 'Subject', '\Entity\MacrotaskTask', 'status', $this );
        $rule9_ViewTask->addAttribute( 'Constant', '', [2,3,4], null );
        $rule9_ViewTask->addRelation('not in');
        $this->rulesContainer->attach($rule9_ViewTask, 'taskStatusIsOpen');

        $policy_viewTask = $this->permissions->addPolicy('viewTask');
        $policy_viewTask->addRule( $this->getRuleWithInfo('userIsKurator'), 'oneof' );
        $policy_viewTask->addRule( $this->getRuleWithInfo('userIsIniciator'), 'oneof' );
        $policy_viewTask->addRule( $this->getRuleWithInfo('userDepartmentIsOUKD'), 'oneof' );
        $policy_viewTask->addRule( $this->getRuleWithInfo('userIsWorker'), 'oneof' );
        $policy_viewTask->addRule( $this->getRuleWithInfo('userInWhiteList'), 'oneof' );

        $policy_editTask = $this->permissions->addPolicy('editTask');
        $policy_editTask->addRule( $this->getRuleWithInfo('userIsKurator'), 'oneof' );
        $policy_editTask->addRule( $this->getRuleWithInfo('userIsIniciator'), 'oneof' );
        $policy_editTask->addRule( $this->getRuleWithInfo('userDepartmentIsOUKD'), 'oneof' );
        $policy_editTask->addRule( $this->getRuleWithInfo('userInWhiteList'), 'oneof' );
        $policy_editTask->addRule( $this->getRuleWithInfo('taskStatusIsOpen'), 'necessiarly' );

        $policy_btn_returnTaskByPerformer = $this->permissions->addPolicy('returnTaskByPerformer');
        $policy_btn_returnTaskByPerformer->addRule( $this->getRuleWithInfo('userIsWorker'), 'necessiarly' );
        $policy_btn_returnTaskByPerformer->addRule( $this->getRuleWithInfo('taskStatusIsCloseByUsers'), 'necessiarly' );

//        $policy_leaveTask = $this->permissions->addPolicy('performerleaveTask');
//        $policy_leaveTask->addRule( $this->getRuleWithInfo('userIsWorker'), 'necessiarly' );
//        $policy_leaveTask->addRule( $this->getRuleWithInfo('performersInTaskMoreThan1'), 'necessiarly' );
//        $policy_leaveTask->addRule( $this->getRuleWithInfo('performerNoLeaveTask'), 'necessiarly' );

        $policy_addPerformerInTask = $this->permissions->addPolicy('addPerformerInTask');
        $policy_addPerformerInTask->addRule( $this->getRuleWithInfo('taskStatusIsOpen'), 'necessiarly' );

    }

    public function index(){
        $time_start = microtime(true);

        if (isset($_GET['nkeep'])){
            $this->nkeepPageIndex();
            return;
        }

         // задачи с участием текущего пользователя в качестве исполнителя
        $data['multitasks'] =       Entity\MacrotaskTask::getMyTasksWithStatus('performer',1);//->groupBy('agreement.task_id');
        $data['done_multitask'] =   Entity\MacrotaskTask::getMyTasksWithStatus('performer',2);
        $data['closemultitasks'] =  Entity\MacrotaskTask::getMyTasksWithStatus('performer',3);
         // задачи с участием текущего пользователя в качестве инициатора
        $data['taskWhoInsert'] =      Entity\MacrotaskTask::getMyTasksWithStatus('iniciator',1);
        $data['taskWhoInsertDone'] =  Entity\MacrotaskTask::getMyTasksWithStatus('iniciator',2);
        $data['taskWhoInsertClose'] = Entity\MacrotaskTask::getMyTasksWithStatus('iniciator',3);

        $data['countTasksActive'] = $data['multitasks']->count(); //$this->multitasking_model->getTasksActiveCount(USER_COOKIE_ID);
        $data['countTasksDone'] = $data['done_multitask']->count(); //$this->multitasking_model->getDonetaskCount(USER_COOKIE_ID);
        $data['countTasksClose'] = $data['closemultitasks']->count(); //$this->multitasking_model->getTasksCloseCount(USER_COOKIE_ID);
        $data['taskWhoInsertCount'] = $data['taskWhoInsert']->count(); //$this->multitasking_model->getWhoInsertActiveCount(USER_COOKIE_ID);
        $data['taskWhoInsertCloseCount'] = $data['taskWhoInsertClose']->count(); //$this->multitasking_model->getWhoInsertCloseCount(USER_COOKIE_ID);
        $data['countInsertTasksDone'] = $data['taskWhoInsertDone']->count(); //$this->multitasking_model->getInsertDonetaskCount(USER_COOKIE_ID);

        $data['title'] = 'Мои задачи';
        $data['user_session'] = $this->employee_model->getSesionUser(USER_COOKIE_ID);
        $data['user_cookie_id'] = USER_COOKIE_ID;
        $data['view_tasks_my_department'] = $this->permission_model->viewMyDepartment(USER_COOKIE_ID);

        if (isset($data['view_tasks_my_department'][0]->department_id)) {
            $department_id = $data['view_tasks_my_department'][0]->department_id;
        } else {
            $department_id = 1;
        }
        // Данные для печати
        $data['print_surname'] = $data['user_session'][0]->surname;
        $data['print_name'] = $data['user_session'][0]->name;
        $data['print_middle_name'] = $data['user_session'][0]->middlename;

        $data['employee'] = $this->users_model->getAll();
        //$data['multitask'] = 'multitask';
        $this->view_library->allViewLibAndQuery('multitask', 'multitask', $data);

        $time_end = microtime(true);
        $time = $time_end - $time_start;
    }

    public function nkeepPageIndex(){
        $this->load->model('work_model');
        $works = $this->work_model->getAllworks_ForUser(USER_COOKIE_ID);
        //$works = $this->multitasking_model->getTaskMyAll(USER_COOKIE_ID);

        $data['works'] = $this->sortPortletPositions($works);
        $data['quantityInColumn'] = $this->user_setting_model->getSettingValue('quantityInColumn', USER_COOKIE_ID);
        $data['nkeep'] = 'nkeep';
        $this->view_library->allViewLibAndQuery('multitask', 'draggable', $data);
    }

    public function updatePortletPositions(){
        if (isset($_POST["positions"])&&!empty($_POST["positions"])) {
            $positions = $_POST["positions"];
            foreach ($positions as $k=>$pos){ $positions[$k] = preg_replace("/[^0-9]/", '', $pos); }
            $this->load->model('user_setting_model');
            $this->user_setting_model->setSetting('portlet position', $positions, 1, 1, USER_COOKIE_ID);

            $incol[] = $_POST["col1"]; $incol[] = $_POST["col2"]; $incol[] = $_POST["col3"]; $incol[] = $_POST["col4"];
            $this->user_setting_model->setSetting('quantityInColumn', $incol, 1, 1, USER_COOKIE_ID);

            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function sortPortletPositions($works)
    {
        $this->load->model('user_setting_model');
        $positions = $this->user_setting_model->getSettingValue('portlet position', USER_COOKIE_ID);

        if (!empty($positions)) {
            foreach ($positions as $pos) {
                foreach ($works as $work) {
                    if ($work['wrk_id'] == $pos) {
                        if ($work['color'] == null) {
                            $work['color'] = 'rgb(240,240,240)';
                        }
                        $result_arr[] = $work;
                    }
                }
            }
        }

        if (isset($result_arr)){
            return $result_arr;
        } else {
            return $works;
        }
    }

    public function setPortletSettingColor(){
        if(isset($_POST['work_id'])){
            $this->load->model("work_setting_model");
            $data = array(
                "work_id"           => $_POST["work_id"],
                "color"             => $_POST["color"],
                "user_id"           => USER_COOKIE_ID
            );
            $this->work_setting_model->setWorkSetting($data);
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    // обновление или добавление нового таймера
    public function setWorkTimer(){

        echo json_encode(array('status' => 'success'));
    }

    /**
     * обязательные:
     * date_begin
     * date_period
     * full
     * priority
     * iniciator
     * responsible - массив ID исполнителей
     *
     * необязательные:
     * $_FILES
     * select_type_object
     * select_object
     * arenda_object
     * avtopark_object
     * kurator
     * date_time_begin
     *
     * return json :
     * 'status' => 'success', 'task_id' => {ID добавленной задачи}
     * 'status' => 'error'
     * 'status' => 'error_size'
     * 'status' => 'invert_minus'
     * 'status' => 'invert_cannot'
     *
     */
    public function add(){
        if (isset($_POST) && sizeof($_POST) > 0) {

            if ($this->isMobile){
                if(!empty($_FILES)) {
                    $name = "image";
                    $name_folder = "./assets/tasks_documents/";
                    $filename = $_FILES[$name]['name']; // Получаем изображение
                    $size = $_FILES[$name]['size'];
                    if($size > MAX_SIZE_PICTURE){ // 8 Mb - максимум
                        echo json_encode(array('status' => 'error_size')); // Размер картинки или файла превышает 8 Мегабайт
                        exit;
                    }
                    $ext = substr($filename, 1 + strrpos($filename, ".")); // получаем его расширение
                    $image_filename = md5(uniqid(rand(), 1)) . "." . $ext; // придумываем ему новое имя и в конце добавляем к нему расширение и получается полноценный файл
                    $upload_file = $name_folder . $image_filename;
                    if (! move_uploaded_file($_FILES[$name]["tmp_name"], $upload_file)) {
                        echo json_encode(array('status' => 'error')); // Не удалось загрузить картинку или документ
                        exit;
                    }
                    //$this->download_library->saveFileServer($name, $name_folder);
                    $post['image'] = $image_filename;
                } else {
                    $post['image'] = '';
                }
            } else {
                $getFilesComment = $this->multitasking_model->getFilesCommentBd(USER_COOKIE_ID);
                $countF = count($getFilesComment);
                for ($j = 0; $j < $countF; $j++) {
                    $new_images[$j] = $getFilesComment[$j]->name_server;
                }
                if (isset($new_images)) {
                    $post['image'] = serialize($new_images);
                } else {
                    $post['image'] = '';
                }
            }

            $getDepartment = $this->multitasking_model->getDepartmentUser(USER_COOKIE_ID);
            if ($getDepartment[0]->active == 0) {
                $post['assepted'] = 2;
            }

            if (isset($_POST['select_type_object']) && !empty($_POST['select_type_object'])) {
                if($_POST['select_type_object'] == 1){
                    $post['points_id'] = $this->secur_library->addPost($_POST['select_object'], 1);
                } else if($_POST['select_type_object'] == 2){
                    $post['points_id'] = $this->secur_library->addPost($_POST['arenda_object'], 1);
                } else if($_POST['select_type_object'] == 3){
                    $post['avtopark_id'] = $this->secur_library->addPost($_POST['avtopark_object'], 1);
                }
            } else if(empty($_POST['select_object']) && empty($_POST['arenda_object']) && empty($_POST['avtopark_object'])){
                $post['points_id'] = 0;
            } else {
                $post['points_id'] = 0;
            }

            $time_begin = $_POST['date_begin'];
            if (isset($_POST['date_time_begin'])) {
                $time_begin .= $_POST['date_time_begin'];
            }
            $post['date_begin'] = date("Y-m-d H:i:s", strtotime($time_begin));
            $post['full'] = $this->secur_library->addPost($_POST['full'], 0);
            $post['date'] = date("Y-m-d H:i:s");
            $stringDateP = explode(" ", $_POST['date_period']);
            $dateP = $stringDateP[0];
            $dateP .= $stringDateP[1];
            $post['date_period'] = date("Y-m-d H:i:s", strtotime($dateP));
            $post['status'] = STATUS_TASK_1;
            $post['priority'] = $this->secur_library->addPost($_POST['priority'], 1);
            $post['iniciator'] = $this->secur_library->addPost($_POST['iniciator'], 1);
            if (isset($_POST['kurator']) && !empty($_POST['kurator'])) {
                $post['kurator'] = $this->secur_library->addPost($_POST['kurator'], 1);
                $kurator = $post['kurator'];
            } else {
                $post['kurator'] = 0;
                $kurator = '';
            }
            $post['author'] = USER_COOKIE_ID;
            $datetime1 = date_create($post['date_begin']);
            $datetime2 = date_create($post['date_period']);
            $interval = date_diff($datetime1, $datetime2);
            if($interval->invert == 1){
                echo json_encode(array('status' => 'invert_minus'));exit;
            } elseif($interval->h == 1 && $interval->i == 0 && $interval->d == 0){
                echo json_encode(array('status' => 'invert_cannot'));exit;
            } elseif($interval->h == 0 && $interval->d == 0){
                echo json_encode(array('status' => 'invert_cannot'));exit;
            }

            $array['responsible'] = $_POST['responsible'];
            $result = $this->multitasking_model->addMultitask($post, $array);
            $getTaskForNotification = $this->multitasking_model->getTaskForNotification($result);
            $getEmployeeCook = $this->employee_model->getUserForCookie(USER_COOKIE_ID);
            $getResponsibleNoti = $this->multitasking_model->getResponsibleNotiBd($array['responsible']);

            $this->task = \Entity\MacrotaskTask::find($result);
            // добавим задачу и её исполнителей.
            $this->task->addPerformers( $array['responsible'] );

            if (!empty($result)) {
                $this->multitasking_model->deleteFileCommentAll(USER_COOKIE_ID);
                $pusher = $this->pusher_library->connect_push();
                $count = count($getTaskForNotification);
                $type_task_push = 0;
                $type_task_for_user = 0;
                $current_status_task = 1;
                if(empty($getTaskForNotification[0]->street) && empty($getTaskForNotification[0]->building)){
                    $telegram_text = urlencode("<b>".$getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." добавил(а) новую задачу!</b>\nНомер задачи - " . $getTaskForNotification[0]->multitask_id . "\nОписание - " . $this->wrapperText($_POST['full']) . "\nДата назначения - " .  date("d.m.Y H:i", strtotime($post['date'])) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($time_begin)) . "\nДата исполнения - " . $post['date_period'] . "\nИсполнители:\n ".$getResponsibleNoti);
                } else {
                    $telegram_text = urlencode("<b>".$getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." добавил(а) новую задачу!</b>\nНомер задачи - " . $getTaskForNotification[0]->multitask_id . "\nОписание - " . $this->wrapperText($_POST['full']) . "\nОбъект - ".$getTaskForNotification[0]->street." ".$getTaskForNotification[0]->building."\nДата назначения - " .  date("d.m.Y H:i", strtotime($post['date'])) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($time_begin)) . "\nДата исполнения - " . $post['date_period'] . "\nИсполнители:\n ".$getResponsibleNoti);
                }
                for ($i = 0; $i < $count; $i++) {
                    $new_task_noti = $this->modules_model->getNEwTask($array['responsible'][$i], 1);
                    if (!empty($new_task_noti)) {
                        $status_task = 0;
                        $pusher->trigger('notifications_' . $array['responsible'][$i], 'tasks_notification', $getTaskForNotification);
                        if (!empty($getTaskForNotification[$i]->telegram_id)) {
                            $this->traits_library->connectAndSendPush($getTaskForNotification[$i]->telegram_id,$telegram_text, $result, $status_task, $type_task_push, $type_task_for_user, 0, 1, 0, $current_status_task);
                        }
                    }
                    // Отправка push уведомления на телефон(андроид)
                    //$text = $getEmployeeCook[0]->surname." добавил задачу №".$getTaskForNotification[0]->multitask_id." : ".$getTaskForNotification[0]->multitask_full;
                    //$pusher->trigger('notifications_android_user_'.$array['responsible'][$i], 'notification_android', array('title' => "Новая задача", 'content' => $text, 'description' => 'У вас есть новая задача'));
                }
                // Отправка уведомления специально для ЕВ
                if($post['priority'] == 4){
                    $status_task = 1;
                    $this->traits_library->connectAndSendPush(TELEGRAM_ID_USER_EV,$telegram_text, $result, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);
                    $this->traits_library->connectAndSendPush(TELEGRAM_ID_USER_ARTUR,$telegram_text, $result, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);
                }
                echo json_encode(array('status' => 'success', 'task_id' => $result));
            } else {
                echo json_encode(array('status' => 'error'));
            }
        } else {
            $this->multitasking_model->deleteFileCommentAll(USER_COOKIE_ID);
            $this->traits_library->doneTaskRedirect();
            $data['title'] = 'Задачи';
            $data['select_permission'] = $this->permissions_library->closeAccess('addNewTask');
            $data['department'] = $this->employee_model->getDepartment();
            $data['employee'] = $this->users_model->getEmployed();
            $data['torg_object'] = $this->multitasking_model->getAllObjectsTorg();
            $data['arenda_object'] = $this->multitasking_model->getTorgObjects();
            $data['avtopark'] = $this->multitasking_model->getAvtopark();
            $data['multitask_add'] = 'multitask_add';
            $this->view_library->allViewLibAndQuery('multitask', 'add', $data);
        }
    }

    public function search(){
        $post['responsible'] = $this->secur_library->addPost($_POST['responsible'], 0);
        $result = $this->multitasking_model->getPersonal($post);
        if (empty($result)) {
            echo json_encode(array('status' => 'error', 'value' => 'null'));
        } else {
            echo json_encode(array('status' => 'success', 'value' => $result));
        }
    }

    /**
     * Редактирование задачи
     *
     * */
    public function edit($task_id = null){
        if (!is_numeric($task_id)) {
            show_404();
        }

        $this->task = \Entity\MacrotaskTask::find( $task_id );

        if (isset($_POST) && sizeof($_POST) > 0) {

            if (!empty($_FILES)) {
                $name = 'document';
                $name_folder = './assets/tasks_documents/';
                $post['image'] = $this->download_library->saveFileServer($name, $name_folder);
            }
            if (!empty($_POST['date_begin'])) {
                $new_time_begin = $_POST['date_begin'];
                $new_time_begin .= $_POST['date_time_begin'];
                $post['date_begin'] = date("Y-m-d H:i:s", strtotime($new_time_begin));
            }
            if(isset($_POST['date']) && isset($_POST['date_time'])){
                $new_time = $_POST['date'];
                $new_time .= $_POST['date_time'];
            }
            if (isset($_POST['moder_status']) && $_POST['moder_status'] != 0) {
                $post['moder'] = strip_tags($_POST['moder_status']);
                $post['date_approved'] = date("Y-m-d H:i:s");
            }
            if(isset($_POST['date']) && isset($_POST['date_time']) && !empty($_POST['date']) && !empty($_POST['date_time'])){
                $post['date_period'] = date("Y-m-d H:i:s", strtotime($new_time));
            }
            $post['full'] = $this->secur_library->addPost($_POST['full'], 0);
            if (isset($_POST['short'])) {
                $post['status'] = $_POST['status'];
            }
            if (isset($_POST['select_resp']) && !empty($_POST['select_resp'])) {
                $new_respons['who_insert_id'] = $this->secur_library->addPost($_POST['select_resp'], 1);
                $post['iniciator'] = $new_respons['who_insert_id'];
            } else {
                $new_respons['who_insert_id'] = '';
                $post['iniciator'] = '';
            }
            $post['priority'] = $this->secur_library->addPost($_POST['priority'], 1);
            if (isset($_POST['select_object'])) {
                $post['points_id'] = $this->secur_library->addPost($_POST['select_object'], 1);
            } else {
                $post['points_id'] = 0;
            }
            if (isset($_POST['responsible']) && !empty($_POST['responsible'])) {
                $array['responsible'] = array_unique($_POST['responsible']);
            } else {
                $array['responsible'] = null;
            }
            foreach ($array['responsible'] as $item) {
                $arr[] = $item;
            }
            if (isset($_POST['kurator']) && !empty($_POST['kurator'])) {
                $post['kurator'] = $this->secur_library->addPost($_POST['kurator'], 1);
            } else {
                $post['kurator'] = '';
            }

            // обновим список Исполнителей задачи
            $this->task->updatePerformers( $array['responsible'] );

            $edit_multitask = $this->multitasking_model->getTask($task_id);
            $review_multitask_employee = $this->multitasking_model->getForTasksEmployeeForLog($task_id);
            $getWhoInsertID = $this->multitasking_model->getWhoInsertID($edit_multitask[0]->who_insert_id, $task_id);
            $getNewResponsible = $this->employee_model->getUsersForUserId($new_respons['who_insert_id']);
            $getNewEmployeeLog = $this->multitasking_model->getNewEmployeeLog($arr);
            $new_string = "";
            foreach ($getNewEmployeeLog as $item) {
                $new_string = $new_string . ";" . $item;
            }
            if ($edit_multitask[0]->multitask_full != $post['full']) {
                $log['old_version_full'] = $edit_multitask[0]->multitask_full;
                $log['new_version_full'] = $post['full'];
            }
            if ($edit_multitask[0]->points_id != $post['points_id']) {
                //array_push($log, $edit_multitask[0]->points_id);
                $log['old_version_points_id'] = $edit_multitask[0]->points_street . " " . $edit_multitask[0]->points_building;
                $log['new_version_points_id'] = $this->secur_library->addPost($_POST['nameObject'], 1);
            }
            // todo Переделать. Неверный алгоритм!!!
            if (count($array['responsible']) != count($review_multitask_employee)) {
                // Записываем новых исполнителей в лог по задачам
                $log['old_version_responsible'] = $review_multitask_employee;
                $log['new_version_responsible'] = $new_string;
            }
            if(isset($post['date_period']) && !empty($post['date_period'])){
                if ($edit_multitask[0]->multitask_date_period != $post['date_period']) {
                    $log['old_version_date_period'] = $edit_multitask[0]->multitask_date_period;
                    $log['new_version_date_period'] = $post['date_period'];
                }
            }
            if ($edit_multitask[0]->multitask_priority != $post['priority']) {
                $log['old_version_priority'] = $edit_multitask[0]->multitask_priority;
                $log['new_version_priority'] = $post['priority'];
            }
            if ($edit_multitask[0]->who_insert_id != $new_respons['who_insert_id']) {
                $log['old_version_inisiator'] = $getWhoInsertID;
                $log['new_version_inisiator'] = $getNewResponsible;
            }
            if (isset($log)) {
                $log = serialize($log);
            } else {
                $log = null;
            }
            $result = $this->multitasking_model->editTask($post, $task_id, $arr, $log, $new_respons);
            $edit_multitask = $this->multitasking_model->getForTasksEmployee($task_id);
            if ($result == true) {
                $pusher = $this->pusher_library->connect_push();
                if (!empty($arr)) {
                    $count = count($arr);
                    for ($i = 0; $i < $count; $i++) {
                        $new_task_edit = $this->modules_model->getNEwTask($arr[$i], 5);
                        if (!empty($new_task_edit)) {
                            $pusher->trigger('notifications_' . $arr[$i], 'edit_task_noti', array('task_id' => $task_id));
                        }
                    }
                }
                $getEmployeeCook = $this->employee_model->getUserForCookie(USER_COOKIE_ID);
                $getTaskForNotification = $this->multitasking_model->getTaskForNotification($task_id);
                $getResponsibleNoti = $this->multitasking_model->getResponsibleNotiBd($array['responsible']);
                $count = count($getTaskForNotification);
                $type_task_push = 0;
                $type_task_for_user = 0;
                $current_status_task = $getTaskForNotification[0]->mult_status;
                if(empty($getTaskForNotification[0]->street) && empty($getTaskForNotification[0]->building)){
                    $telegram_text = urlencode("<b>".$getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." отредактировал(а) задачу!</b>\nНомер задачи - " . $getTaskForNotification[0]->multitask_id . "\nОписание - " . $this->wrapperText($_POST['full']) . "\nДата назначения - " .  date("d.m.Y H:i", strtotime($getTaskForNotification[0]->date_nn)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($getTaskForNotification[0]->date_b)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($getTaskForNotification[0]->date_pp)) . "\nИсполнители:\n".$getResponsibleNoti);
                } else {
                    $telegram_text = urlencode("<b>".$getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." отредактировал(а) задачу!</b>\nНомер задачи - " . $getTaskForNotification[0]->multitask_id . "\nОписание - " . $this->wrapperText($_POST['full']) . "\nОбъект - ".$getTaskForNotification[0]->street." ".$getTaskForNotification[0]->building."\nДата назначения - " .  date("d.m.Y H:i", strtotime($getTaskForNotification[0]->date_nn)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($getTaskForNotification[0]->date_b)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($getTaskForNotification[0]->date_pp)) . "\nИсполнители:\n".$getResponsibleNoti);
                }
                for ($i = 0; $i < $count; $i++) {
                    //$new_task_noti = $this->modules_model->getNEwTask($array['responsible'][$i], 1);
                    //if (!empty($new_task_noti)) {
                        $status_task = 0;
                        $pusher->trigger('notifications_' . $arr[$i], 'tasks_notification', $getTaskForNotification);
                        if (!empty($getTaskForNotification[$i]->telegram_id)) {
                            $this->traits_library->connectAndSendPush($getTaskForNotification[$i]->telegram_id,$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 0, 1, 0, $current_status_task);
                        }
                    //}
                }
                if($post['priority'] == 4){
                    $status_task = 1;
                    $this->traits_library->connectAndSendPush(TELEGRAM_ID_USER_EV,$telegram_text, $result, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);
                    $this->traits_library->connectAndSendPush(TELEGRAM_ID_USER_ARTUR,$telegram_text, $result, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);
                }

                echo json_encode(array('status' => 'success'));
            } else {
                echo json_encode(array('status' => 'error'));
            }
        } else {
            $check_EditTask_Policy = $this->permissions->checkPolicy('editTask');

            if ( $check_EditTask_Policy ){
                //$data['edit_multitask'] = $this->multitasking_model->getTask($task_id);
                $data['task'] = $this->task;
                $data['isIniciator'] = ($this->task->iniciator == USER_COOKIE_ID) ? true : false;
                $data['isKurator'] = ($this->task->kurator == USER_COOKIE_ID) ? true : false;
                $data['title'] = 'Задача №' . $task_id;
                $data['department'] = $this->employee_model->getDepartment();
                $data['employee'] = $this->users_model->getEmployed();
                $data['review_multitask_employee'] = $this->multitasking_model->getForTasksEmployeeEdit($task_id);
                $data['all_objects'] = $this->multitasking_model->getAllObjects();
                $data['get_edit_objects'] = $this->multitasking_model->getEditObjects($task_id);
                $this->view_library->allViewLibAndQuery('multitask', 'edit', $data);
            } else {
                show_404();
            }
        }
    }

    public function delete(){
        if (!is_numeric($_POST['task_id'])) {
            show_404();
        } else {
            $result = $this->multitasking_model->deleteTask($_POST['task_id']);
            if ($result == 'true') {
                echo json_encode(array('status' => 'success'));
            } else {
                echo json_encode(array('status' => 'error'));
            }
        }
    }

    public function review($task_id = null){
        if (!is_numeric($task_id)) {
            show_404();
        }
        $this->multitasking_model->deleteNotiComment($task_id);
        if (isset($_POST) && sizeof($_POST) > 0) {
            //$this->multitasking_model->reviewTasksLog($task_id, USER_COOKIE_ID);
            if (isset($_POST['recall']) && isset($_POST['date_recall'])) {
                if ($this->secur_library->addPost($_POST['recall'], 1) == 1 && $this->secur_library->addPost($_POST['date_recall'], 0) < date('Y-m-d H:i:s')) {
                    $data['recall'] = 0;
                    $data['date_recall'] = '';
                    $this->multitasking_model->updateRecall($task_id, USER_COOKIE_ID, $data);
                }
            }
            $getDeclaim = $this->multitasking_model->getDeclaim($task_id);
            if ($getDeclaim == 0) {
                $post['date_declaim'] = date('Y-m-d H:i:s');
                $post['declaim'] = 1;
                $result = $this->multitasking_model->actionDeclaim($post, $task_id);
                $this->multitasking_model->deleteReminderTaskUser($task_id, USER_COOKIE_ID);
                if ($result == 'true') {
                    echo json_encode(array('status' => 'success'));
                } else {
                    echo json_encode(array('status' => 'error'));
                }
            } else {
                $status_change_text['change_content'] = 0;
                $this->multitasking_model->updateChangeContent($task_id, $status_change_text, USER_COOKIE_ID);
                $this->multitasking_model->deleteReminderTaskUser($task_id, USER_COOKIE_ID);
                echo json_encode(array('status' => 'success'));
            }
        } else {

            $this->task = \Entity\MacrotaskTask::find( $task_id );
            $data['task'] = $this->task;

            if(empty($data['task'])){
                show_404();
            } else {

                //todo В нотификацию это!
                $this->multitasking_model->deleteReminderTaskUser($task_id, USER_COOKIE_ID);
                //----------------------


                $check_viewTask_Policy = $this->permissions->checkPolicy('viewTask');

                if ( !$check_viewTask_Policy ){
                    show_404();
                } else {

                    $macrotask = \Entity\Macrotask::where('wrk_macrotaskid', '=', $this->task->id)->where('wrk_type', '=', 'task')->get();
                    $macrotask->load('Performers.Workuser.User.Employee.Position');
                    $data['task_performers'] = array();
                    foreach ($macrotask[0]->Performers as $performer) {
                        $user = new stdClass();
                        $user->user_id = $performer->workuser->user->id;
                        $user->position = $performer->workuser->user->employee->position->name;
                        $user->employee = $performer->workuser->user->employee;
                        $data['task_performers'][] = $user;
                    }

                    // приоритет задачи в доп инфо в хедер
                    if($this->task->priority == PRIORITY_TASK_1){
                        $data['additionalTitle'] = '<span class="label label-info addlabelinfo_reviewtask">Низкий</span>';
                    } elseif($this->task->priority  == PRIORITY_TASK_2){
                        $data['additionalTitle'] = '<span class="label label-warning addlabelinfo_reviewtask">Средний</span>';
                    } elseif($this->task->priority  == PRIORITY_TASK_3){
                        $data['additionalTitle'] = '<span class="label label-danger addlabelinfo_reviewtask">Высокий</span>';
                    } elseif($this->task->priority == PRIORITY_TASK_4){
                        $data['additionalTitle'] = '<span class="label label-primary addlabelinfo_reviewtask">Приоритет ЕВ</span>';
                    }

                    if($this->task->status == 2 || $this->task->status == 3 || $this->task->status == 4) {
                        $datetime2 = date_create($this->task->date_period);
                        if ($this->task->date_perform != '0000-00-00 00:00:00') {
                            $datetime1 = date_create($this->task->date_perform);
                        } else {
                            $datetime1 = date_create($this->task->date_close);
                        }
                        $interval = date_diff($datetime1, $datetime2);
                        if ($interval->y != 0) { $interval_year = $interval->y . ' г. '; } else { $interval_year = ''; }
                        if ($interval->m != 0) { $interval_month = $interval->m . ' мес. '; } else { $interval_month = ''; }
                        if ($interval->d != 0) { $interval_day = $interval->d . ' дн. '; } else { $interval_day = ''; }
                        if ($interval->h != 0) { $interval_hour = $interval->h . ' ч. '; } else { $interval_hour = ''; }
                        if ($interval->i != 0) { $interval_min = $interval->i . ' мин. '; } else { $interval_min = ''; }
                        if ($interval->invert == 1) {
                            $data['estimated_time'] = '<span class="btn default btn-xs red-stripe estimated_fail"> Просрочена ' . $interval_year . $interval_month . $interval_day . $interval_hour .$interval_min . '</span>';
                        } elseif ($interval->invert == 0) {
                            $data['estimated_time'] = '';//'<span class="btn default btn-xs green-stripe estimated_cool"> Выполнена в срок ' . $interval_year . $interval_month . $interval_day . $interval_hour .$interval_min . '</span>';
                        }
                    } else {
                        $data['estimated_time'] = '';
                    }

                    if (isset($data['task']->kurator) && !empty($data['task']->kurator)) {
                        $data['review_multitask_curator'] = $this->multitasking_model->getTaskCurator($task_id);
                        $data['task_kurator'] = \Entity\User::with('Employee.Position')->where('id', $this->task->kurator)->get()[0];
                    }
                    $data['checkMyTasks'] = $this->multitasking_model->checkViewTaskMy($task_id, USER_COOKIE_ID);
                    $data['view_tasks_my_department'] = $this->permission_model->viewMyDepartment(USER_COOKIE_ID);

                    $data['review_multitask_for_type_task'] = $this->multitasking_model->getTaskFOrTypeTask($task_id);
                    $data['review_multitask_close_user'] = $this->multitasking_model->getTaskCloseUser($task_id);
                    //$data['review_multitask_employee'] = $this->multitasking_model->getForTasksEmployee($task_id);
                    $data['review_multitask_employee_count'] = $this->multitasking_model->getForTasksEmployeeCount($task_id);
                    $data['review_multitask_who_insert'] = $this->multitasking_model->getWhoInsertTask($task_id);
                    $data['task_iniciator'] = \Entity\User::with('Employee.Position')->where('id', $this->task->iniciator)->get()[0];
                    //$data['taskKurator'] = $this->multitasking_model->getTaskCurator($task_id);
                    $data['user_id_session'] = get_cookie('id', TRUE);
                    $data['close_tasks_director'] = $this->permission_model->closeTasksDirectorPermisions(USER_COOKIE_ID);
                    $data['quickComments'] = $this->multitasking_model->getQuickComments(USER_COOKIE_ID);
                    $data['reviewTaskLogs'] = $this->multitasking_model->getReviewTaskLogs($task_id);
                    $data['getLinkModul'] = $this->multitasking_model->getLinkModulBd(USER_COOKIE_ID);
                    $data['spoki_noki'] = $this->multitasking_model->getLogSpokiNoki($task_id);
                    $data['help_log_task'] = $this->multitasking_model->getLogHelpTask($task_id);
                    $data['hashUser'] = $this->multitasking_model->getHashUser(USER_COOKIE_ID);
                    $data['allNotesForTask'] = $this->multitasking_model->getallNotesForTask($task_id);
                    $data['comment_oykd'] = $this->multitasking_model->getCommentOykd($task_id);
                    $data['logsTask'] = $this->multitasking_model->getLogsTask($task_id);
                    $data['getRecall'] = $this->multitasking_model->getRecall($task_id, USER_COOKIE_ID);
                    $data['getUsersVoiceExit'] = $this->multitasking_model->getUsersVoiceExit($task_id);
                    $data['getUsersVoiceExitStart'] = $this->multitasking_model->getUsersVoiceExitStart($task_id);
                    $data['getAllUsersExitTask'] = $this->multitasking_model->getAllUsersExitTask($task_id);
                    $data['voiceStartUser'] = $this->multitasking_model->voiceStartUser($task_id);
                    $data['getSettings'] = $this->multitasking_model->getSettings($task_id);

                    // обработка ссылок в теле комментариев
                    $all_tasks = $this->multitasking_model->getTasks($task_id, "DESC");
                    foreach ($all_tasks as $k => $atask){
                        if (!empty($atask->text)) {
                            if (!empty($this->is_urla($atask->text))) {
                                $all_tasks[$k]->text = $this->is_urla($atask->text);
                            }
                        }
                        if (!empty($atask->answer_text)) {
                            if (!empty($this->is_urla($atask->answer_text))) {
                                $all_tasks[$k]->answer_text = $this->is_urla($atask->answer_text);
                            }
                        }
                    }
                    $data['all_tasks'] = $all_tasks;

                    $this->multitasking_model->deleteFileCommentAll(USER_COOKIE_ID);

                    $data['task_id'] = $task_id;
                    $data['title'] = 'Задача №' . $task_id;
                    $data['multitask_review'] = 'multitask_review';
                    $this->view_library->allViewLibAndQuery('multitask', 'review', $data);
                }
            }
        }
    }

    public function getCountAllFiles(){
        $allFileCommentUser = $this->multitasking_model->allFileCommentUser(USER_COOKIE_ID);
        echo json_encode(array('status' => 'success', 'count' => $allFileCommentUser));
    }

    public function updateStatusWhoInsert($task_id = null){
        if (!is_numeric($task_id)) {
            show_404();
        } else {
            //$this->multitasking_model->reviewTasksLog($task_id, USER_COOKIE_ID);
            $status_change_text['change_content_out'] = 0;
            $this->multitasking_model->updateChangeContentWhoInsert($task_id, $status_change_text, USER_COOKIE_ID);
            echo json_encode(array('status' => 'success'));
        }
    }

    public function addFilesComment(){
        //echo "<pre>";print_r($task_id);exit;
        $filename = $_FILES['file']['name'];  // Получаем изображение
        $type = $_FILES['file']['type'];
        $size = $_FILES['file']['size'];
        //echo "<pre>";print_r($type);exit;
        if ($size > MAX_SIZE_PICTURE) {
            echo json_encode(array('status' => 'error_size')); // Размер картинки или файла превышает 8 Мегабайт
            exit;
        } elseif ($type != 'image/jpeg' && $type != 'image/png' && $type != 'image/jpg' && $type != 'text/plain' && $type != 'audio/mp3' && $type != 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' && $type != 'application/msword' && $type != 'audio/mp3' && $type != 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' && $type != 'application/pdf' && $type != 'application/vnd.ms-excel' && $type != 'application/vnd.ms-project' && $type != 'application/vnd.ms-excel.sheet.macroEnabled.12' && $type != 'video/mpeg' && $type != 'application/vnd.openxmlformats-officedocument.presentationml.presentation' && $type != 'video/mp4' && $type != 'application/x-zip-compressed') {
            echo json_encode(array('status' => 'error_no_correct')); // Неккоректный тип изображения или документа
            exit;
        }
        $ext = substr($filename, 1 + strrpos($filename, ".")); // получаем его расширение
        $image_filename = md5(uniqid(rand(), 1)) . "." . $ext; // придумываем ему новое имя и в конце добавляем к нему расширение и получается полноценный файл
        $upload_dir = './assets/tasks_documents/';
        $upload_file = $upload_dir . $image_filename;
        if (!move_uploaded_file($_FILES['file']["tmp_name"], $upload_file)) {
            echo json_encode(array('status' => 'error')); // Не удалось загрузить картинку или документ
            exit;
        }
        $post['user_id'] = USER_COOKIE_ID;
        $post['name'] = $filename;
        $post['name_server'] = $image_filename;
        $this->multitasking_model->insertFileComment($post);
    }

    public function removeFilesComment(){
        //echo "<pre>";print_r($_POST);exit;
        $post['user_id'] = $this->secur_library->addPost($_POST['user_id'], 1);
        $post['name'] = $this->secur_library->addPost($_POST['file'], 0);
        $this->multitasking_model->deleteFileComment($post);
    }

    public function addComment($task_id = null){
        //echo "<pre>";print_r($_POST);exit;
        //echo "<pre>";print_r($_FILES);exit;
        if (!is_numeric($task_id)) {
            show_404();
        }
        if (empty($this->secur_library->addPost($_POST['comment'], 0))) {
            echo json_encode(array('status' => 'no_exists_comment'));
            exit;
        }
        $getFilesComment = $this->multitasking_model->getFilesCommentBd(USER_COOKIE_ID);
        $countF = count($getFilesComment);
        for($j = 0; $j < $countF; $j++){
            $new_images[$j] = $getFilesComment[$j]->name_server;
        }
        if (isset($new_images)) {
            $post['files'] = serialize($new_images);
        } else {
            $post['files'] = '';
        }

        //echo "<pre>";print_r($post);exit;
        $post['task_id'] = $task_id;
        $post['user_id'] = USER_COOKIE_ID;
        $post['text'] = nl2br(htmlspecialchars(strip_tags($_POST['comment'], '<p><a><b><i><li><ul>')));
        $post['date'] = date("Y-m-d H:i:s");
        //echo "<pre>";print_r($post['text']);exit;
        $data['user_department'] = $this->secur_library->addPost($_POST['user_department'], 0);
        //echo "<pre>";print_r($post);exit;
        $type_task = $this->secur_library->addPost($_POST['type_task'], 1);
        $respons = $this->multitasking_model->getForTasksEmployee($task_id);
        $iniciator = $this->multitasking_model->getWhoInsertTask($task_id);
        $respons_task_noti = $this->multitasking_model->getForTasksEmployeeNoti($task_id, USER_COOKIE_ID);
        $iniciator_task_spoki = $this->multitasking_model->getWhoInsertTaskNoti($task_id, USER_COOKIE_ID);
        $getResponsNoti = $this->multitasking_model->getResponForNoti($task_id);
        //echo "<pre>";print_r($getResponsNoti);echo "</pre>";exit;
        //echo "<pre>";print_r($iniciator_task_spoki);exit;
        $result = $this->multitasking_model->addComment($post, $data, $type_task, $respons, $iniciator);
        $getEmployeeCook = $this->employee_model->getUserForCookie(USER_COOKIE_ID);
        //echo "<pre>";print_r($result);exit;
        if (isset($result[0]->files)) {
            $all_img = unserialize($result[0]->files);
        }
        $result[0]->images_all = $all_img;
        //echo "<pre>";print_r($result);exit;
        if (!empty($result)) {
            echo json_encode(array('status' => 'success'));
            $this->multitasking_model->deleteFileCommentAll(USER_COOKIE_ID);

            $pusher = $this->pusher_library->connect_push();

            $pusher->trigger('test_channel_' . $task_id, 'comment', $result[0]);
            $type_task_push = 0;
            $current_status_task = $result[0]->status;
            if(empty($result[0]->street) && empty($result[0]->building)){
                $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." добавил(а) новый комментарий:\n<b>".$this->wrapperText($post['text']) . "</b>\nНомер задачи - " . $result[0]->task_id . "\nОписание - " . $this->wrapperText($result[0]->full) . "\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]->mult_date)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($result[0]->date_period)) . "\nИсполнители:\n".$getResponsNoti);
            } else {
                $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." добавил(а) новый комментарий:\n<b>".$this->wrapperText($post['text']) . "</b>\nНомер задачи - " . $result[0]->task_id . "\nОписание - " . $this->wrapperText($result[0]->full) . "\nОбъект - ".$result[0]->street." ".$result[0]->building."\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]->mult_date)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($result[0]->date_period)) . "\nИсполнители:\n".$getResponsNoti);
            }
            if (!empty($respons_task_noti)) {
                $iniciator = 0;
                $task_add = 1;
                $sendComment= 0;
                $status_task = 0;
                $type_task_for_user = 0;
                foreach ($respons_task_noti as $noti) {
                    $new_comment_noti = $this->modules_model->getNEwTask($noti['user_id'], 2);
                    if (!empty($new_comment_noti)) {
                        $pusher->trigger('notifications_' . $noti['user_id'], 'comment_task_notification', $result[0]);
                        if (!empty($noti['telegram_id'])) {
                            $this->traits_library->connectAndSendPush($noti['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, $iniciator, $task_add, $sendComment, $current_status_task);
                        }
                    }
                }
            }

            if (!empty($iniciator_task_spoki)) {
                $status_task = 0;
                $iniciator = 1;
                $task_add = 0;
                $sendComment= 1;
                $type_task_for_user = 1;
                $new_comment_noti = $this->modules_model->getNEwTask($iniciator_task_spoki[0]['user_id'], 2);
                if (!empty($new_comment_noti)) {
                    $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'comment_task_notification', $result[0]);
                    if (!empty($iniciator_task_spoki[0]['telegram_id'])) {
                        $this->traits_library->connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, $iniciator, $task_add, $sendComment, $current_status_task);
                    }
                }
            }
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function addCommentApi(){
        $telegram_id = (int)$_GET['telegram_id'];
        $text = $_GET['text'];
        $getTaskObj = $this->multitasking_model->getTaskIdIsActionTable($telegram_id);
        //echo "<pre>";print_r($getTaskObj);exit;
        $task_id = $getTaskObj[0]->task_id;
        $post['files'] = '';
        $getUserId = $this->employee_model->getUserIdFoTelegramId($telegram_id);
        $post['task_id'] = $task_id;
        $post['user_id'] = $getUserId[0]->id;
        $post['text'] = nl2br(htmlspecialchars(strip_tags($text, '<p><a><b><i><li><ul>')));
        $post['date'] = date("Y-m-d H:i:s");
        $data['user_department'] = 0;
        $type_task = 1;
        $respons = $this->multitasking_model->getForTasksEmployee($task_id);
        $iniciator = $this->multitasking_model->getWhoInsertTask($task_id);
        $respons_task_noti = $this->multitasking_model->getForTasksEmployeeNoti($task_id, USER_COOKIE_ID);
        $iniciator_task_spoki = $this->multitasking_model->getWhoInsertTaskNoti($task_id, USER_COOKIE_ID);
        $result = $this->multitasking_model->addComment($post, $data, $type_task, $respons, $iniciator);
        if (isset($result[0]->files)) {
            $all_img = unserialize($result[0]->files);
        }
        $result[0]->images_all = $all_img;
        //echo "<pre>";print_r($result);exit;
        if (!empty($result)) {
            echo json_encode(array('status' => 'success'));

            $pusher = $this->pusher_library->connect_push();

            $pusher->trigger('test_channel_' . $task_id, 'comment', $result[0]);

            $telegram_text = "У вас новый комментарий! Описание: " . $this->wrapperText($post['text']) ."";
            $status_task = 0;
            $type_task_push = 0;
            $type_task_for_user = 0;
            if (!empty($respons_task_noti)) {
                foreach ($respons_task_noti as $noti) {
                    $new_comment_noti = $this->modules_model->getNEwTask($noti['user_id'], 2);
                    if (!empty($new_comment_noti)) {
                        $pusher->trigger('notifications_' . $noti['user_id'], 'comment_task_notification', $result[0]);
                        if (!empty($noti['telegram_id'])) {
                            $this->traits_library->connectAndSendPush($noti['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, 0);
                        }
                    }
                }
            }

            if (!empty($iniciator_task_spoki)) {
                $new_comment_noti = $this->modules_model->getNEwTask($iniciator_task_spoki[0]['user_id'], 2);
                if (!empty($new_comment_noti)) {
                    $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'comment_task_notification', $result[0]);
                    if (!empty($iniciator_task_spoki[0]['telegram_id'])) {
                        $this->traits_library->connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 1, 0, 0, 0);
                    }
                }
            }
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function comment_answer(){
        $post['user_id'] = USER_COOKIE_ID;
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        $post['comment_id'] = $this->secur_library->addPost($_POST['comm_id'], 1);
        $post['text'] = $this->secur_library->addPost($_POST['text'], 0);
        $post['date'] = date('Y-m-d H:i:s');
        //echo "<pre>";print_r($post['text']);exit;
        $result = $this->multitasking_model->addCommentAnswer($post, $task_id);
        $status_task = 0;
        $type_task_push = 0;
        $change_status_task = $result[0]['mult_status'];
        $getEmployeeCook = $this->employee_model->getUserForCookie(USER_COOKIE_ID);
        $getResponsNoti = $this->multitasking_model->getResponForNoti($task_id);
        if(empty($result[0]['street']) && empty($result[0]['building'])){
            $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." ответил(а) на комментарий:\n<b>".$this->wrapperText($post['text'])."</b>\nНомер задачи - " . $result[0]['mult_id'] . "\nОписание - " . $this->wrapperText($result[0]['full']) . "\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]['date_write'])) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]['date_begin'])) . "\nДата исполнения - " . date("d.m.Y H:i", strtotime($result[0]['date_period'])) . "\nИсполнители:\n".$getResponsNoti);
        } else {
            $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." ответил(а) на комментарий:\n<b>".$this->wrapperText($post['text'])."</b>\nНомер задачи - " . $result[0]['mult_id'] . "\nОписание - " . $this->wrapperText($result[0]['full']) . "\nОбъект - ".$result[0]['street']." ".$result[0]['building']."\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]['date_write'])) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]['date_begin'])) . "\nДата исполнения - " . date("d.m.Y H:i", strtotime($result[0]['date_period'])) . "\nИсполнители:\n".$getResponsNoti);
        }
        $respons_task_noti = $this->multitasking_model->getForTasksEmployeeNoti($task_id, USER_COOKIE_ID);
        $iniciator_task_spoki = $this->multitasking_model->getWhoInsertTaskNoti($task_id, USER_COOKIE_ID);
        if (!empty($result)) {
            $pusher = $this->pusher_library->connect_push();
            if (!empty($respons_task_noti)) {
                $type_task_for_user = 0;
                $iniciator = 0;
                $task_add = 1;
                $sendComment= 0;
                foreach ($respons_task_noti as $noti) {
                    $pusher->trigger('notifications_' . $noti['user_id'], 'comment_task_notification', $result[0]);
                    $this->traits_library->connectAndSendPush($noti['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, $iniciator, $task_add, $sendComment, $change_status_task);
                }
            }
            if (!empty($iniciator_task_spoki)) {
                $type_task_for_user = 1;
                $iniciator = 1;
                $task_add = 0;
                $sendComment= 1;
                $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'comment_task_notification', $result[0]);
                $this->traits_library->connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user,$iniciator, $task_add, $sendComment, $change_status_task);
            }
            echo json_encode(array('status' => 'success', 'result' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function all_tasks(){
        //$time_start = microtime(true);
        //WorkInMultitask::doneTaskRedirect();
        $this->traits_library->doneTaskRedirect();
        $time_start = microtime(true);
        $data['title'] = 'Задачи';
        //$data['multitask'] = 'multitask';
        $data['multitask_all'] = 'multitask_all';
        $data['select_department'] = $this->multitasking_model->getSelectAllDepartment();
        $data['delete_task_admin'] = $this->permission_model->deleteTasksPermisions(USER_COOKIE_ID);
        $data['objects_for_sort'] = $this->multitasking_model->getObjectsForSort();
        $data['brand'] = $this->multitasking_model->getBrand();
        $data['employee_sort'] = $this->users_model->getAll();
        if (!isset($_COOKIE['light'])) {
            $data['electTasks'] = $this->multitasking_model->getElectTasksUser();
        }
        if (isset($_COOKIE['department'])) {
            $post['department'] = $_COOKIE['department'];
        } else {
            $post['department'] = null;
        }
        if (isset($_COOKIE['employee'])) {
            $post['employee'] = $_COOKIE['employee'];
        } else {
            $post['employee'] = null;
        }
        if (isset($_COOKIE['status'])) {
            $post['status'] = $_COOKIE['status'];
        } else {
            $post['status'] = null;
        }
        if (isset($_COOKIE['executor'])) {
            $post['executor'] = $_COOKIE['executor'];
        } else {
            $post['executor'] = null;
        }
        if (isset($_COOKIE['komentator'])) {
            $post['komentator'] = $_COOKIE['komentator'];
        } else {
            $post['komentator'] = null;
        }
        if (isset($_COOKIE['object'])) {
            $post['object'] = $_COOKIE['object'];
        } else {
            $post['object'] = null;
        }
        if (isset($_COOKIE['brand'])) {
            $post['brand'] = $_COOKIE['brand'];
        } else {
            $post['brand'] = null;
        }
        if (isset($_COOKIE['priority'])) {
            $post['priority'] = $_COOKIE['priority'];
        } else {
            $post['priority'] = null;
        }
        if(isset($_COOKIE['from'])){
            $post['from'] = date("Y-m-d", strtotime($_COOKIE['from']));
        } else {
            $post['from'] = null;
        }
        if(isset($_COOKIE['to'])){
            $post['to'] = date("Y-m-d", strtotime($_COOKIE['to']));
        } else {
            $post['to'] = null;
        }
        if(isset($_COOKIE['search'])){
            $post['search'] = $_COOKIE['search'];
        } else {
            $post['search'] = null;
        }
        //echo "<pre>";print_r($post);exit;
        if (isset($_COOKIE['load'])) {
            $load['load'] = (int)abs($_COOKIE['load']);
        } else {
            $load['load'] = 0;
        }


        $data['selectTasksEmployees']['result'] = $this->multitasking_model->getTasksAllUsers($post, $load);
//        $time_start = microtime(true);
        $data['selectTasksEmployees']['count'] = $this->multitasking_model->countGetTasksAllUsers($post, $load);
//        $time_end = microtime(true);
//        $time = $time_end - $time_start;
//        print_r($time);
        $data['count_page_ll'] = ceil($data['selectTasksEmployees']['count'] / COUNT_LOAD_TASKS);
        //echo "<pre>";print_r($data['selectTasksEmployees']['count']);
        //echo "<pre>";print_r($data['count_page_ll']);exit;

        $data['count_tasks'] = count($data['selectTasksEmployees']['result']);
        $data['multitask'] = 'multitask';
        $this->view_library->allViewLibAndQuery('multitask', 'all_tasks', $data);

        //echo "<br>";

    }

    public function sortTask(){
        $ot = strip_tags($_POST['ot']);
        $to = strip_tags($_POST['to']);
        $post['ot'] = date("Y-m-d", strtotime($ot));
        $post['to'] = date("Y-m-d", strtotime($to));
        $result = $this->multitasking_model->getPeriodDate($post);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'result' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function declaim(){
        if (!is_numeric($_POST['task_id'])) {
            show_404();
        }
        $post['assepted'] = $this->secur_library->addPost($_POST['status'], 0);
        if ($_POST['status'] == 2) {
            $post['date_accepted'] = date('Y-m-d H:i:s');
        }
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        if (!empty($_POST['modal_comment'])) {
            $comment['text'] = $this->secur_library->addPost($_POST['modal_comment'], 0);
            $result = $this->multitasking_model->addAssepted($post, $task_id, $comment);
        } else {
            $comment['text'] = '';
            $result = $this->multitasking_model->addAssepted($post, $task_id, $comment);
        }
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }


    public function createPrintUsers(){
        if (isset($_POST['select_status']) && !empty($_POST['select_status'])) {
            $post['select_status'] = $this->secur_library->addPost($_POST['select_status'], 0);
        } else {
            $post['select_status'] = '';
        }
        $post['user_id'] = $_POST['select_print_employee'];
        $result = $this->multitasking_model->getEmployeeFoPrint($post);
        //echo "<pre>";print_r($result);exit;

        $getTaskEV = $this->multitasking_model->getAllTaskEVPriority($post);
        $getTaskEVCount = $this->multitasking_model->getAllTaskEVPriorityCount($post);
        $getTaskPriorityHigh = $this->multitasking_model->getAllTaskHighForUsersMyTasks($post);
        $getTaskPriorityHighCount = $this->multitasking_model->getAllTaskHighForUsersCount($post);
        $getTaskPriorityNoAverage = $this->multitasking_model->getAllTaskNoAveragetForUsers($post);
        $getTaskPriorityNoAverageCount = $this->multitasking_model->getAllTaskNoAverageCountForUsers($post);
        echo json_encode(array('status' => 'success', 'result' => $result[0], 'getTaskEV' => $getTaskEV, 'getTaskEVCount' => $getTaskEVCount, 'getTask' => $getTaskPriorityHigh, 'getTaskCount' => $getTaskPriorityHighCount[0], 'getStandart' => $getTaskPriorityNoAverage, 'getStandartCount' => $getTaskPriorityNoAverageCount[0]));
        //echo "<pre>";print_r($getTask);exit;
    }

    // Печать во вкладке "Мои задачи"
    public function createPrintMyTasks(){
        $post['user_id'] = get_cookie('id', TRUE);
        $result = $this->multitasking_model->getEmployeeFoPrint($post);
        $getTaskEV = $this->multitasking_model->getTaskEVPriority($post);
        $getTaskEVCount = $this->multitasking_model->getTaskEVPriorityCount($post);
        $getTaskPriorityHigh = $this->multitasking_model->getTaskHighForUsersMyTasks($post);
        $getTaskPriorityHighCount = $this->multitasking_model->getTaskHighForUsersCount($post);
        $getTaskPriorityNoAverage = $this->multitasking_model->getTaskNoAveragetForUsers($post);
        $getTaskPriorityNoAverageCount = $this->multitasking_model->getTaskNoAverageCountForUsers($post);
        echo json_encode(array('status' => 'success', 'result' => $result[0], 'getTaskEV' => $getTaskEV, 'getTaskEVCount' => $getTaskEVCount, 'getTask' => $getTaskPriorityHigh, 'getTaskCount' => $getTaskPriorityHighCount[0], 'getStandart' => $getTaskPriorityNoAverage, 'getStandartCount' => $getTaskPriorityNoAverageCount[0]));
    }

    /*
     * Печать как одного сотрудника, так и нескольких. Метод - createPrintUsers можно убрать и заменить на этот.
     * Входные парметры - $_POST['select_employee'], где передается масив сотрудников
     * */
    public function createPrintGroupUsers(){
        if (isset($_POST['select_status']) && !empty($_POST['select_status'])) {
            $post['select_status'] = $this->secur_library->addPost($_POST['select_status'], 1);
        } else {
            $post['select_status'] = '';
        }
        $post['select_employee'] = $_POST['select_employee'];
        $getEmployeeSelect = $this->multitasking_model->getEmployeeSelectPrint($post);

        $getTaskPriorityHigh = $this->multitasking_model->getTaskPriorityHigh($post);
        $getTaskPriorityAverageAndLowAllUsers = $this->multitasking_model->getTaskPriorityAverageAndLow($post);

        echo json_encode(array('status' => 'success', 'getEmployeeSelect' => $getEmployeeSelect, 'getTaskPriorityHigh' => $getTaskPriorityHigh, 'getTaskPriorityAverageAndLowAllUsers' => $getTaskPriorityAverageAndLowAllUsers));
    }


    public function createPrintDepartment(){
        $post['select_department'] = $_POST['select_department'];
        $getEmployeeSelect = $this->multitasking_model->getSelectDepartmentOnEmployee($post);
        $new_array = array();
        foreach ($getEmployeeSelect[0] as $item) {
            array_push($new_array, $item->user_id);
        }
        $getTaskPriorityHigh = $this->multitasking_model->getSelectDepartmentTasksHigh($new_array);
        $getTaskPriorityAverageAndLowAllUsers = $this->multitasking_model->getSelectDepartmentTasksAverageAndLow($new_array);
        echo json_encode(array('status' => 'success', 'getEmployeeSelect' => $getEmployeeSelect, 'getTaskPriorityHigh' => $getTaskPriorityHigh, 'getTaskPriorityAverageAndLowAllUsers' => $getTaskPriorityAverageAndLowAllUsers));
    }

    public function createPrintReportEmployee(){
        $post['select_employee'] = $_POST['report'];
        $getEmployeeSelect = $this->multitasking_model->getSelectEmployeeOnReport($post);
        $getTaskReport = $this->multitasking_model->getTaskReport($post);
        echo json_encode(array('status' => 'success', 'getEmployeeSelect' => $getEmployeeSelect, 'getTasksReport' => $getTaskReport));
    }

    public function sendCommentOYKD(){
        $post['user_id'] = get_cookie('id', TRUE);
        $post['task_id'] = $this->secur_library->addPost($_POST['task_id'], 1);
        $post['comment'] = $this->secur_library->addPost($_POST['text'], 0);
        $post['date'] = date('Y-m-d H:i:s');
        $result = $this->multitasking_model->addCommentOYKD($post);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'result' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function closeTask(){
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        $post['status'] = $this->secur_library->addPost($_POST['status'], 1);
        $priority = $this->secur_library->addPost($_POST['priority'], 1);
        $post['date_close'] = date('Y-m-d H:i:s');
        $data['task_id'] = $task_id;
        $data['user_id'] = USER_COOKIE_ID;
        if(!empty($_POST['text'])){
            $data['text'] = $this->secur_library->addPost($_POST['text'], 0);
        } else {
            $data['text'] = "Задача закрыта!";
        }
        $data['date'] = date('Y-m-d H:i:s');

        if (!empty($_FILES)) {
            $count = count($_FILES);
            //echo "<pre>";print_r($_FILES);exit;
            for ($i = 0; $i < $count; $i++) {
                $filename = $_FILES['document_' . $i . '']['name'];  // Получаем изображение
                $type = $_FILES['document_' . $i . '']['type'];
                //echo "<pre>";print_r($type);exit;
                $size = $_FILES['document_' . $i . '']['size'];
                if ($size > MAX_SIZE_PICTURE) {
                    echo json_encode(array('status' => 'error_size')); // Размер картинки или файла превышает 8 Мегабайт
                    exit;
                } elseif ($type != 'image/jpeg' && $type != 'image/png' && $type != 'image/jpg' && $type != 'text/plain' && $type != 'audio/mp3' && $type != 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' && $type != 'application/msword' && $type != 'audio/mp3' && $type != 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' && $type != 'application/pdf' && $type != 'application/vnd.ms-excel' && $type != 'application/vnd.ms-project' && $type != 'application/vnd.ms-excel.sheet.macroEnabled.12' && $type != 'video/mpeg') {
                    echo json_encode(array('status' => 'error_no_correct')); // Неккоректный тип изображения или документа
                    exit;
                }
                $ext = substr($filename, 1 + strrpos($filename, ".")); // получаем его расширение
                $image_filename = md5(uniqid(rand(), 1)) . "." . $ext; // придумываем ему новое имя и в конце добавляем к нему расширение и получается полноценный файл
                $upload_dir = './assets/tasks_documents/';
                $upload_file = $upload_dir . $image_filename;
                if (!move_uploaded_file($_FILES['document_' . $i . '']["tmp_name"], $upload_file)) {
                    echo json_encode(array('status' => 'error')); // Не удалось загрузить картинку или документ
                    exit;
                }
                $new_images[$i] = $image_filename;
            }
        }

        if (isset($new_images)) {
            $data['files'] = serialize($new_images);
        } else {
            $data['files'] = '';
        }


        $respons_task_noti = $this->multitasking_model->getForTasksEmployeeNoti($task_id, USER_COOKIE_ID);
        $iniciator_task_spoki = $this->multitasking_model->getWhoInsertTaskNoti($task_id, USER_COOKIE_ID);
        $result = $this->multitasking_model->updateStatusClose($post, $task_id, $data, $priority);
        $getResponsNoti = $this->multitasking_model->getResponForNoti($task_id);
        //echo "<pre>";print_r($result);exit;
        if (!empty($result)) {
            $getEmployeeCook = $this->employee_model->getUserForCookie(USER_COOKIE_ID);
            $type_task_push = 0;
            if($result[0]->date_perform != "0000-00-00 00:00:00"){
                if(empty($result[0]->street) && empty($result[0]->building)){
                    $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." закрыл(а) задачу:\n<b>".$this->wrapperText($data['text'])."</b>\nНомер задачи - " . $result[0]->mult_id . "\nОписание - " . $this->wrapperText($result[0]->full) . "\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]->date_write)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_period)) . "\nДата выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_perform)) . "\nДата закрытия - " . date("d.m.Y H:i", strtotime($result[0]->date_close)) . " \nИсполнители:\n".$getResponsNoti);
                } else {
                    $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." закрыл(а) задачу:\n<b>".$this->wrapperText($data['text'])."</b>\nНомер задачи - " . $result[0]->mult_id . "\nОписание - " . $this->wrapperText($result[0]->full) . "\nОбъект - ".$result[0]->street." ".$result[0]->building."\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]->date_write)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_period)) . "\nДата выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_perform)) . "\nДата закрытия - " . date("d.m.Y H:i", strtotime($result[0]->date_close)) . " \nИсполнители:\n".$getResponsNoti);
                }
            } else {
                if(empty($result[0]->street) && empty($result[0]->building)){
                    $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." закрыл(а) задачу:\n<b>".$this->wrapperText($data['text'])."</b>\nНомер задачи - " . $result[0]->mult_id . "\nОписание - " . $this->wrapperText($result[0]->full) . "\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]->date_write)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_period)) . "\nДата закрытия - " . date("d.m.Y H:i", strtotime($result[0]->date_close)) . " \nИсполнители:\n".$getResponsNoti);
                } else {
                    $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." закрыл(а) задачу:\n<b>".$this->wrapperText($data['text'])."</b>\nНомер задачи - " . $result[0]->mult_id . "\nОписание - " . $this->wrapperText($result[0]->full) . "\nОбъект - ".$result[0]->street." ".$result[0]->building."\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]->date_write)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_period)) . "\nДата закрытия - " . date("d.m.Y H:i", strtotime($result[0]->date_close)) . " \nИсполнители:\n".$getResponsNoti);
                }
            }
            if(isset($result[0]->text) && !empty($result[0]->text)){
                $telegram_text .= urlencode("<b>\nПоследние 5 комментариев:</b>\n");
                $count_p = 0;
                foreach ($result as $com){
                    $count_p++;
                    if(strip_tags($com->text) == $com->text){
                        $telegram_text .= urlencode($count_p.") ".$this->wrapperText($com->text)."\n");
                    }
                }
            }
            $status_task = 1;
            $type_task_for_user = 0;
            $current_status_task = $result[0]->mult_status;
            $pusher = $this->pusher_library->connect_push();
            if (!empty($respons_task_noti)) {
                foreach ($respons_task_noti as $noti) {
                    $pusher->trigger('notifications_' . $noti['user_id'], 'close_task_notification', array('task_id' => $task_id));
                    $this->traits_library->connectAndSendPush($noti['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);
                }
            }

            if (!empty($iniciator_task_spoki)) {
                $new_close_task_noti = $this->modules_model->getNEwTask($iniciator_task_spoki[0]['user_id'], 3);
                if (!empty($new_close_task_noti)) {
                    $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'close_task_notification', array('task_id' => $task_id));
                    $this->traits_library->connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 1, 0, 0, $current_status_task);
                }
            }
            if(isset($result[0]->priority) && $result[0]->priority == 4){
                //$telegram_text_ev = "Задача была закрыта. №: ". $result[0]->mult_id.",  Кратко: " . $this->wrapperText($result[0]->full) .". <b>Последний комментарий - ".$this->wrapperText($result[0]->text)."<b>";
                $this->traits_library->connectAndSendPush(TELEGRAM_ID_USER_EV,$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);
                $this->traits_library->connectAndSendPush(TELEGRAM_ID_USER_ARTUR,$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);

            }
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function file_force_download($file){
        $filename = './assets/tasks_documents/' . $file;
        $this->download_library->downloadFiles($file, $filename);
    }

    public function selectTasksByDepartment(){
        $post['department'] = $this->secur_library->addPost($_POST['department'], 1);
        $post['moder'] = $this->secur_library->addPost($_POST['moder'], 1);
        $result = $this->multitasking_model->getTasksByDepartment($post);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'result' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function searchTasks(){
        $post['text'] = $this->secur_library->addPost($_POST['text'], 0);
        $result = $this->multitasking_model->searchTaskBd($post);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'value' => $result));
        } else {
            echo json_encode(array('status' => 'success', 'value' => 'null'));
        }
    }

    public function searchMyTasks(){
        $post['text'] = $this->secur_library->addPost($_POST['text'], 0);
        $post['type'] = $this->secur_library->addPost($_POST['type'], 0);
        //echo "<pre>";print_r($post);exit;
        $post['user_id'] = get_cookie('id', TRUE);
        $result = $this->multitasking_model->searchMyTaskBd($post);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'value' => $result));
        } else {
            echo json_encode(array('status' => 'success', 'value' => 'null'));
        }
    }

    public function deleteTaskAdmin(){
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        $data['trash'] = 1;
        $result = $this->multitasking_model->deleteOrReestablishTask($task_id, $data);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function resstablishTask(){
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        $data['trash'] = 0;
        $result = $this->multitasking_model->deleteOrReestablishTask($task_id, $data);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function viewReminderTask(){
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        //$this->multitasking_model->reviewTasksLog($task_id, USER_COOKIE_ID);
        $result = $this->multitasking_model->deleteReminderTaskUser($task_id, USER_COOKIE_ID);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function viewCommentNotification(){
        $comment_id = $this->secur_library->addPost($_POST['comment_id'], 1);
        $result = $this->multitasking_model->deleteNotificationOykd($comment_id, USER_COOKIE_ID);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function viewAllNotification(){
        $result = $this->multitasking_model->deleteNotificationOykdAll(USER_COOKIE_ID);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function closeDirector(){
        if(isset($_POST) && sizeof($_POST) > 0){
            $task_id = (int)$_POST['task_id'];
            if ($_POST['status'] == 0) {
                $post['status'] = 1;
                $post['date_close'] = '';
                $comment['task_id'] = $task_id;
                $comment['user_id'] = USER_COOKIE_ID;
                $comment['text'] = "Задача возвращена";
                $comment['files'] = "";
                $comment['date'] = date('Y-m-d H:i:s');
            } else if ($_POST['status'] == 1) {
                $post['status'] = 4;
                $post['date_close_director'] = date('Y-m-d H:i:s');
                $comment['task_id'] = $task_id;
                $comment['user_id'] = USER_COOKIE_ID;
                $comment['text'] = "Задача закрыта";
                $comment['files'] = "";
                $comment['date'] = date('Y-m-d H:i:s');
            }
        }

        $result = $this->multitasking_model->closeDirectorTasks($post, $task_id, $comment);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function selectEmployeeForOykd(){
        $department_id = $this->secur_library->addPost($_POST['department'], 1);
        $result = $this->multitasking_model->getSelectEmployee($department_id);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'employee' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function selectTasksForOykdCash(){
        $post['user'] = $this->secur_library->addPost($_POST['selectEmployeForOykd'], 1);
        if ($_POST['statusForOykd'] == 1) {
            $post['status'] = 0;
            $post['status'] = 2;
        } else if ($_POST['statusForOykd'] == 2) {
            $post['status'] = 3;
        } else if ($_POST['statusForOykd'] == 3) {
            $post['status'] = 3;
        }
        $result = $this->multitasking_model->getSelectTasksForOykdCash($post);
    }

    public function actionOverMyTasks($task_id = null){
        if ($_POST['action'] == 'print') {
            $data['date_declaim'] = date('Y-m-d H:i:s');
            $data['declaim'] = 1;
        } elseif ($_POST['action'] == 'no_print') {
            $data['declaim'] = 0;
        }
        $result = $this->multitasking_model->updateDeclaim($task_id, $data, USER_COOKIE_ID);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success', 'result' => $_POST['action'], 'date' => date('d.m.Y H:i')));
        }
    }

    public function closeTaskUser(){
        //echo "<pre>";print_r($_POST);exit;
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        if (!empty($_FILES)) {
            $count = count($_FILES);
            //echo "<pre>";print_r($_FILES);exit;
            for ($i = 0; $i < $count; $i++) {
                $filename = $_FILES['document_' . $i . '']['name'];  // Получаем изображение
                $type = $_FILES['document_' . $i . '']['type'];
                //echo "<pre>";print_r($type);exit;
                $size = $_FILES['document_' . $i . '']['size'];
                if ($size > MAX_SIZE_PICTURE) {
                    echo json_encode(array('status' => 'error_size')); // Размер картинки или файла превышает 8 Мегабайт
                    exit;
                } elseif ($type != 'image/jpeg' && $type != 'image/png' && $type != 'image/jpg' && $type != 'text/plain' && $type != 'audio/mp3' && $type != 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' && $type != 'application/msword' && $type != 'audio/mp3' && $type != 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' && $type != 'application/pdf' && $type != 'application/vnd.ms-excel' && $type != 'application/vnd.ms-project' && $type != 'application/vnd.ms-excel.sheet.macroEnabled.12' && $type != 'video/mpeg') {
                    echo json_encode(array('status' => 'error_no_correct')); // Неккоректный тип изображения или документа
                    exit;
                }
                $ext = substr($filename, 1 + strrpos($filename, ".")); // получаем его расширение
                $image_filename = md5(uniqid(rand(), 1)) . "." . $ext; // придумываем ему новое имя и в конце добавляем к нему расширение и получается полноценный файл
                $upload_dir = './assets/tasks_documents/';
                $upload_file = $upload_dir . $image_filename;
                if (!move_uploaded_file($_FILES['document_' . $i . '']["tmp_name"], $upload_file)) {
                    echo json_encode(array('status' => 'error')); // Не удалось загрузить картинку или документ
                    exit;
                }
                $new_images[$i] = $image_filename;
            }
        }

        if (isset($new_images)) {
            $post['files'] = serialize($new_images);
        } else {
            $post['files'] = '';
        }
        //echo "<pre>";print_r($post);exit;
        if (!empty($_POST['text'])) {
            $post['task_id'] = $task_id;
            $post['user_id'] = get_cookie('id', TRUE);
            $post['text'] = $this->secur_library->addPost($_POST['text'], 0);
            $post['date'] = date('Y-m-d H:i:s');
        } else {
            $post['text'] = '';
        }
        $data['status'] = $this->secur_library->addPost($_POST['close_status'], 1);
        if ($data['status'] == 2) {
            $data['date_perform'] = date('Y-m-d H:i:s');
            $close_user['close_users'] = 1;
            $close_user['date_declaim'] = date('Y-m-d H:i:s');
        } elseif ($data['status'] == 1) {
            $data['date_perform'] = '';
            $close_user['close_users'] = 0;
            $close_user['date_declaim'] = '';
        }
        //echo "<pre>";print_r($post['text']);exit;
        $respons_task_noti = $this->multitasking_model->getForTasksEmployeeNoti($task_id, USER_COOKIE_ID);
        $iniciator_task_spoki = $this->multitasking_model->getWhoInsertTaskNoti($task_id, USER_COOKIE_ID);
        $user_id = USER_COOKIE_ID;
        $result = $this->multitasking_model->updateStatusCloseTaskUsers($task_id, $data, $post, $close_user, $user_id);
        //echo "<pre>";print_r($result);exit;
        $getEmployeeCook = $this->employee_model->getUserForCookie(USER_COOKIE_ID);
        $getResponsNoti = $this->multitasking_model->getResponForNoti($task_id);
        $sendArray = array(
            'task_id' => $task_id,
            'status' => $this->secur_library->addPost($_POST['close_status'], 1)
        );
        $type_task_push = 0;
        $current_status_task= $result[0]->mult_status;
        if($sendArray['status'] == 1){
            $type_task_for_user= 0;
            $task_add = 1;
            if(empty($result[0]->street) && empty($result[0]->buidling)){
                $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." вернул(а) задачу:\n<b>".$this->wrapperText($post['text']) . "</b>\nНомер задачи - " . $result[0]->task_id . "\nОписание - " . $this->wrapperText($result[0]->full) . "\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]->date)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($result[0]->date_period)) . "\nИсполнители:\n".$getResponsNoti);
            } else {
                $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." вернул(а) задачу:\n<b>".$this->wrapperText($post['text']) . "</b>\nНомер задачи - " . $result[0]->task_id . "\nОписание - " . $this->wrapperText($result[0]->full) . "\nОбъект - ".$result[0]->street." ".$result[0]->building."\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]->date)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($result[0]->date_period)) . "\nИсполнители:\n".$getResponsNoti);
            }
        } else if($sendArray['status'] == 2){
            $type_task_for_user= 1;
            $task_add = 0;
            if(empty($result[0]->street) && empty($result[0]->buidling)){
                $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." отправил(а) задачу на закрытие:\n<b>".$this->wrapperText($post['text']) . "</b>\nНомер задачи - " . $result[0]->task_id . "\nОписание - " . $this->wrapperText($result[0]->full) . "\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]->date)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($result[0]->date_period)) . "\nИсполнители:\n".$getResponsNoti);
            } else {
                $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." отправил(а) задачу на закрытие:\n<b>".$this->wrapperText($post['text']) . "</b>\nНомер задачи - " . $result[0]->task_id . "\nОписание - " . $this->wrapperText($result[0]->full) . "\nОбъект - ".$result[0]->street." ".$result[0]->building."\nДата назначения - " . date("d.m.Y H:i", strtotime($result[0]->date)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($result[0]->date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($result[0]->date_period)) . "\nИсполнители:\n".$getResponsNoti);
            }
        }
        $pusher = $this->pusher_library->connect_push();
        //echo "<pre>";print_r($respons_task_noti);exit;
        if ($result == 'true') {
            if (!empty($respons_task_noti)) {
                foreach ($respons_task_noti as $noti) {
                    $new_reminder_noti = $this->modules_model->getNEwTask($noti['user_id'], 4);
                    if (!empty($new_reminder_noti)) {
                        $status_task = 0;
                        $pusher->trigger('notifications_' . $noti['user_id'], 'send_close_task_notification', $sendArray);
                        $this->traits_library->connectAndSendPush($noti['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 0, $task_add, 0, $current_status_task);
                    }
                }
            }

            if (!empty($iniciator_task_spoki)) {
                $new_reminder_noti = $this->modules_model->getNEwTask($iniciator_task_spoki[0]['user_id'], 4);
                if (!empty($new_reminder_noti)) {
                    $status_task = 0;
                    $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'send_close_task_notification', $sendArray);
                    $this->traits_library->connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 1, $task_add, 0, $current_status_task);
                }
            }
            if(!empty($post['files'])){
                echo json_encode(array('status' => 'success', 'result' => $sendArray['status'], 'files' => $post['files']));
            } else {
                echo json_encode(array('status' => 'success', 'result' => $sendArray['status']));
            }
        } elseif (!empty($result)) {
            //echo "<br>";
            if(!empty($result[0]->files)){
                $result[0]->files = unserialize($result[0]->files);
            }
            //echo "<pre>";print_r($result);exit;

            $pusher->trigger('test_channel_' . $task_id, 'comment_close_task', $result);
            /*if($sendArray['status'] != 1){
                $pusher->trigger('test_channel_' . $task_id, 'comment_close_task', $result);
            }*/
            if (!empty($respons_task_noti)) {
                foreach ($respons_task_noti as $noti) {
                    $new_reminder_noti = $this->modules_model->getNEwTask($noti['user_id'], 4);
                    if (!empty($new_reminder_noti)) {
                        $status_task = 0;
                        $pusher->trigger('notifications_' . $noti['user_id'], 'send_close_task_notification', $sendArray);
                        $this->traits_library->connectAndSendPush($noti['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 0, $task_add, 0, $current_status_task);
                    }
                }
            }

            if (!empty($iniciator_task_spoki)) {
                $new_reminder_noti = $this->modules_model->getNEwTask($iniciator_task_spoki[0]['user_id'], 4);
                if (!empty($new_reminder_noti)) {
                    $status_task = 0;
                    $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'send_close_task_notification', $sendArray);
                    $this->traits_library->connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 1, $task_add, 0, $current_status_task);
                }
            }
            /*if(isset($result[0]->priority) && $result[0]->priority == 4){
                $status_task = 1;
                $this->traits_library->connectAndSendPush(TELEGRAM_ID_USER_EV,$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);
                $this->traits_library->connectAndSendPush(TELEGRAM_ID_USER_ARTUR,$telegram_text, $task_id, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);
            }*/
            echo json_encode(array('status' => 'success', 'result' => $sendArray['status']));
        }
    }

    public function addRemidersForEmployeesNoOykd(){
        //echo "<pre>";print_r($_POST);exit;
        $post['task_id'] = $this->secur_library->addPost($_POST['task_id'], 1);
        $post['text'] = $this->secur_library->addPost($_POST['text'], 0);
        $post['user_id'] = get_cookie('id', TRUE);
        $post['date'] = date('Y-m-d H:i:s');
        //echo "<pre>";print_r($post);exit;
        $result = $this->multitasking_model->addRemindersForEmployeeForTasks($post);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'result' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function selectExecutor(){
        //echo "<pre>";print_r($_POST);exit;
        $post['user_id'] = $this->secur_library->addPost($_POST['user_id'], 1);
        $result = $this->multitasking_model->getSelectExecutor($post, USER_COOKIE_ID);
        if (!empty($result)) {
            $this->set_cookies_library->setCookies('save_result', $post['user_id'], '94670778');
            echo json_encode(array('status' => 'success', 'value' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function selectExecutorMyTask(){
        //echo "<pre>";print_r($_POST);exit;
        $post['user_id'] = $this->secur_library->addPost($_POST['user_id'], 1);
        $result = $this->multitasking_model->getSelectExecutorMyTasks($post, USER_COOKIE_ID);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'value' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function selectExecutorAllTask(){
        //echo "<pre>";print_r($_POST);exit;
        $post['user_id'] = $this->secur_library->addPost($_POST['user_id'], 1);
        $result = $this->multitasking_model->getSelectExecutorAllTask($post, USER_COOKIE_ID);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'value' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function selectObjectForSortTasks(){
        //echo "<pre>";print_r($_POST);exit;
        $post['object'] = $this->secur_library->addPost($_POST['object'], 1);
        $result = $this->multitasking_model->getSelectObjectForSortTasks($post);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'value' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function selectTasksForCloseOYKD($task_id = null){
        if (!is_numeric($task_id)) {
            show_404();
        } else {
            $data['close_users'] = 1;
            $result = $this->multitasking_model->noticeCloseTasksForOYKD($task_id, $data);
            if ($result == 'true') {
                echo json_encode(array('status' => 'success'));
            }
        }
    }

    public function selectbyBrand(){
        //echo "<pre>";print_r($_POST);exit;
        $post['brand'] = $this->secur_library->addPost($_POST['brand'], 1);
        $result = $this->multitasking_model->getSelectByBrand($post);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'value' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function dateReminder($task_id = null){
        if (!is_numeric($task_id)) {
            show_404();
        }
        $time = explode('T', $_POST['date_reminder']);
        $new_time = $time[0] . ' ' . $time[1];
        $post['date_reminder'] = date("Y-m-d H:i:s", strtotime($new_time));
        $result = $this->multitasking_model->addDateReminder($post, $task_id);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function getNewSelectAllTasks(){
        //echo "<pre>";print_r($_POST);exit;
        if (isset($_POST['type']) && $_POST['type'] != 'loadTask') {
           //WorkInMultitask::clearCookies();
            $this->traits_library->clearCookies();
        }
        $post['department'] = $this->secur_library->addPost($_POST['department'], 1);
        $post['employee'] = $this->secur_library->addPost($_POST['employee'], 1);
        $post['status'] = $this->secur_library->addPost($_POST['status'], 1);
        $post['executor'] = $this->secur_library->addPost($_POST['executor'], 1);
        $post['komentator'] = $this->secur_library->addPost($_POST['komentator'], 1);
        $post['object'] = $this->secur_library->addPost($_POST['object'], 1);
        $post['brand'] = $this->secur_library->addPost($_POST['brand'], 1);
        $post['priority'] = $this->secur_library->addPost($_POST['priority'], 1);
        $post['search'] = $this->secur_library->addPost($_POST['search'], 0);
        $post['from'] = date("Y-m-d", strtotime($_POST['from']));
        $post['to'] = date("Y-m-d", strtotime($_POST['to']));
        //echo "<pre>";print_r($post);exit;

        if (!empty($post['department'])) {
            $this->set_cookies_library->setCookies('department', $post['department'], Traits_library::TASK_FILTER_COOKIE_TIME);
        }
        if (!empty($post['employee'])) {
            $this->set_cookies_library->setCookies('employee', $post['employee'], Traits_library::TASK_FILTER_COOKIE_TIME);
        }
        if (!empty($post['status'])) {
            $this->set_cookies_library->setCookies('status', $post['status'], Traits_library::TASK_FILTER_COOKIE_TIME);
        }
        if (!empty($post['executor'])) {
            $this->set_cookies_library->setCookies('executor', $post['executor'], Traits_library::TASK_FILTER_COOKIE_TIME);
        }
        if (!empty($post['komentator'])) {
            $this->set_cookies_library->setCookies('komentator', $post['komentator'], Traits_library::TASK_FILTER_COOKIE_TIME);
        }
        if (!empty($post['object'])) {
            $this->set_cookies_library->setCookies('object',  $post['object'], Traits_library::TASK_FILTER_COOKIE_TIME);
        }
        if (!empty($post['brand'])) {
            $this->set_cookies_library->setCookies('brand',  $post['brand'], Traits_library::TASK_FILTER_COOKIE_TIME);
        }
        if (!empty($post['priority'])) {
            $this->set_cookies_library->setCookies('priority',  $post['priority'], Traits_library::TASK_FILTER_COOKIE_TIME);
        }
        if (!empty($_POST['from'])) {
            $this->set_cookies_library->setCookies('from',  $_POST['from'], Traits_library::TASK_FILTER_COOKIE_TIME);
        }
        if (!empty($_POST['to'])) {
            $this->set_cookies_library->setCookies('to',  $_POST['to'], Traits_library::TASK_FILTER_COOKIE_TIME);
        }
        if (!empty($post['search'])) {
            $this->set_cookies_library->setCookies('search',  $post['search'], Traits_library::TASK_FILTER_COOKIE_TIME);
        } else {
            delete_cookie('search');
        }

        if ($_POST['type'] == 'newSelect') {
            $this->set_cookies_library->setCookies('load',  0, Traits_library::TASK_FILTER_COOKIE_TIME);
            if (isset($_COOKIE['load'])) {
                $load['load'] = (int)$_COOKIE['load'];
            } else {
                $load['load'] = 0;
            }
        } else {
            if (isset($_COOKIE['load'])) {
                $load['load'] = (int)$_COOKIE['load'];
            }
        }

        $result = 'true'; //$this->multitasking_model->getTasksAllUsers($post, $load);
        $save['parament'] = (object)$post;

        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'value' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function deleteCashUser(){
        //WorkInMultitask::clearCookies();
        $this->traits_library->clearCookies();
        echo json_encode(array('status' => 'success'));
    }

    private function oxoxoAlalaUpdateClockTimeCron(){
        $this->multitasking_model->updateAllTasksActive();
    }

    public function unloadingExcelTasks(){
        require_once './assets/phpexcel/Classes/PHPExcel.php';
        $objPHPExcel = new PHPExcel();

        $objPHPExcel->getProperties()->setCreator('test')
            ->setLastModifiedBy('test')
            ->setTitle('test')
            ->setSubject('test')
            ->setDescription('test')
            ->setKeywords('test')
            ->setCategory('test');
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', '№')
            ->setCellValue('B1', 'Инициатор')
            ->setCellValue('C1', 'Описание')
            ->setCellValue('D1', 'Дата постанвоелния')
            ->setCellValue('E1', 'Дата начала')
            ->setCellValue('F1', 'Дата закрытия')
            ->setCellValue('G1', 'Статус')
            ->setCellValue('H1', 'Приоритет');
        $result = $this->multitasking_model->getTaskExportToExcel(92);
        //echo "<pre>";print_r($result);exit;
        $count = count($result);
        for ($i = 0; $i < $count; $i++) {
            $j = $i + 2;
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A' . $j, $result[$i]->multitask_id)
                ->setCellValue('B' . $j, $result[$i]->employee_surname . ' ' . $result[$i]->employee_name . ' ' . $result[$i]->employee_middle_name)
                ->setCellValue('C' . $j, $result[$i]->multitask_full)
                ->setCellValue('D' . $j, $result[$i]->multitask_date)
                ->setCellValue('E' . $j, $result[$i]->date_begin)
                ->setCellValue('F' . $j, $result[$i]->date_close);
                if($result[$i]->multitask_status == 1){
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('G' . $j, "Текущая");
                } else if($result[$i]->multitask_status == 2){
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('G' . $j, "Выполнена");
                } else if($result[$i]->multitask_status == 3){
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('G' . $j, "Закрыта");
                }
                if($result[$i]->priority == 1){
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('H' . $j, "Низкий");
                } else if($result[$i]->priority == 2){
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('H' . $j, "Средний");
                } else if($result[$i]->priority == 3){
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('H' . $j, "Высокий");
                } else if($result[$i]->priority == 4){
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('H' . $j, "ЕВ");
                }
        }

        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setAutoSize(true);

        // Выводим HTTP-заголовки
        header("Expires: Mon, 1 Apr 1974 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT");
        header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=tasks.xls");

// Выводим содержимое файла
        $objWriter = new PHPExcel_Writer_Excel5($objPHPExcel);
        $objWriter->save('php://output');
        exit();
    }


    public function viewReadOykd(){
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        //$this->multitasking_model->reviewTasksLog($task_id, USER_COOKIE_ID);
        $result = $this->multitasking_model->deleteReadTaskOykd($task_id, USER_COOKIE_ID);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function recall($task_id){
        if (!is_numeric($task_id)) {
            show_404();
        }
        $new_time = $this->secur_library->addPost($_POST['date'], 0);
        $new_time .= $this->secur_library->addPost($_POST['time'], 0);
        $post['date_recall'] = date("Y-m-d H:i:s", strtotime($new_time));

        $curday = date('Y-m-d H:i:s');
        $d1 = strtotime($post['date_recall']);
        $d2 = strtotime($curday);
        $diff = $d1 - $d2;
        $post['recall'] = 1;
        $result = $this->multitasking_model->recall($task_id, $post, USER_COOKIE_ID);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success', 'time' => $diff));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function writeUser(){
        $pusher = $this->pusher_library->connect_push();
        if ($this->secur_library->addPost($_POST['type'], 0) == 'write') {
            $data['user_id'] = $this->secur_library->addPost($_POST['user_id'], 1);
            $data['task_id'] = $this->secur_library->addPost($_POST['task_id'], 1);
            $data['result'] = $this->employee_model->getSesionUser($data['user_id']);
            $pusher->trigger('test_channel_' . $data['task_id'], 'write_user', $data);
        } elseif ($this->secur_library->addPost($_POST['type'], 0) == 'no_write') {
            $data['user_id'] = $this->secur_library->addPost($_POST['user_id'], 1);
            $data['task_id'] = $this->secur_library->addPost($_POST['task_id'], 1);
            $data['result'] = $this->employee_model->getSesionUser($data['user_id']);
            $pusher->trigger('test_channel_' . $data['task_id'], 'no_write', $data);
        } elseif ($this->secur_library->addPost($_POST['type'], 0) == 'reload') {
            $data['user_id'] = $this->secur_library->addPost($_POST['user_id'], 1);
            $data['task_id'] = $this->secur_library->addPost($_POST['task_id'], 1);
            $pusher->trigger('test_channel_' . $data['task_id'], 'reload', $data);
        }
    }

    public function setPage(){
        if (isset($_POST) && sizeof($_POST) > 0) {
            //echo "<pre>";print_r($_POST);exit;
            $page = $this->secur_library->addPost($_POST['page'], 1);
            if ($page == 1) {
                $newpage = '#tab_1_1';
                $data['countTasksActive'] = $this->multitasking_model->getTasksActiveCount(USER_COOKIE_ID);
            } elseif ($page == 2) {
                $newpage = '#tab_1_3';
                $data['countTasksClose'] = $this->multitasking_model->getTasksCloseCount(USER_COOKIE_ID);
            } elseif ($page == 3) {
                $newpage = '#tab_1_5';
                $data['taskWhoInsertCount'] = $this->multitasking_model->getWhoInsertActiveCount(USER_COOKIE_ID);
            } elseif ($page == 4) {
                $newpage = '#tab_1_7';
                $data['taskWhoInsertCloseCount'] = $this->multitasking_model->getWhoInsertCloseCount(USER_COOKIE_ID);
            } elseif ($page == 5) {
                $newpage = '#tab_2_1';
                $data['taskMyAllCount'] = $this->multitasking_model->getTaskMyAllCount(USER_COOKIE_ID);
            } elseif ($page == 6) {
                $newpage = '#tab_2_2';
                $data['view_tasks_my_department'] = $this->permission_model->viewMyDepartment(USER_COOKIE_ID);
                if (isset($data['view_tasks_my_department'][0]->department_id)) {
                    $department_id = $data['view_tasks_my_department'][0]->department_id;
                } else {
                    $department_id = 1;
                }
                $data['tasks_my_departmentCount'] = $this->multitasking_model->getTasksMyDepartmentCount($department_id);
            } elseif ($page == 7) {
                $newpage = '#tab_2_3';
                $data['getTasksItDepartmentCount'] = $this->multitasking_model->getTasksItDepartmentCount(); // Выборка it-отдела
            } elseif ($page == 8) {
                $newpage = '#tab_1_4';
                $data['countTasksDone'] = $this->multitasking_model->getDonetaskCount(USER_COOKIE_ID);
            } elseif ($page == 9) {
                $newpage = '#tab_1_9';
                $data['countInsertTasksDone'] = $this->multitasking_model->getInsertDonetaskCount(USER_COOKIE_ID);
            }
            $this->set_cookies_library->setCookies('page', $page, '2628000');
            $this->set_cookies_library->setCookies('page_link', $newpage, '2628000');

            echo json_encode(array('status' => 'success', 'return' => $newpage, 'data' => $data));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function sortMyTasks(){
        $select_sort = $this->secur_library->addPost($_POST['parametr'], 1);
        $type_number = $_POST['sort'] & 1;
        if ($type_number == 1) {
            $sort = "DESC";
        } elseif ($type_number == 0) {
            $sort = "ASC";
        }

        $result = $this->multitasking_model->getTasksActiveSort(USER_COOKIE_ID, $sort, $select_sort);

        echo json_encode(array('status' => 'success', 'value' => $result));
    }

    public function viewCommentForTask(){
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        $id = $this->secur_library->addPost($_POST['id'], 1);
        $type_task = $this->secur_library->addPost($_POST['type_task'], 1);
        //print_r($type_task);exit;
        if ($type_task == 1) {
            $data['change_content_out'] = 0;
        } elseif ($type_task == 0) {
            $data['change_content'] = 0;
        }

        $this->multitasking_model->updateReviewCommentTask($data, $task_id, $id);
        echo json_encode(array('status' => 'success'));
    }

    public function viewCommentForTaskAnswer(){
        $id = $this->secur_library->addPost($_POST['id'], 1);
        //print_r($type_task);exit;
        $this->multitasking_model->updateReviewCommentTaskAnswer($id, USER_COOKIE_ID);
        echo json_encode(array('status' => 'success'));
    }

    public function helpTask($task_id = null)
    {
        if (isset($_POST) && sizeof($_POST) > 0) {
            if (!is_numeric($task_id)) {
                show_404();
            } else {

                $this->task = \Entity\MacrotaskTask::find( $task_id );

                if( !$this->permissions->checkPolicy('addPerformerInTask') ) {
                    echo json_encode(array('status' => 'no_permission'));
                } else {
                    $post['new_respons'] = $_POST['new_respons'];
                    $getCountUser = $this->multitasking_model->checkUsersExists($post, $task_id);
                    if($getCountUser > 0) {
                        echo json_encode(array('status' => 'users_exists'));
                    } else {
                        $who_ins = $_POST['who_insert'];
                        $result = $this->multitasking_model->insertHelpRespons($post, $task_id, $who_ins);
                        $getEmployeeCook = $this->employee_model->getUserForCookie(USER_COOKIE_ID);
                        if ($result == 'true') {
                            $this->multitasking_model->insertNewHelpUser($post, $task_id);

                            // получим текущих исполнителей
                            $performers = $this->task->getProperty('array_user_performers');
                            foreach ($performers as $performer){
                                $arr_performers[] = $performer->user_id;
                            }
                            // склеим всех исполнителей
                                $newPerformersList = array_merge($_POST['new_respons'], $arr_performers );
                            // обновим список
                            $this->task->updatePerformers( $newPerformersList );

                            $getTaskForNotification = $this->multitasking_model->getTask($task_id);
                            $getTelegramId = $this->multitasking_model->getAllUsersHelp($post, $task_id);
                            $getTelegramIdForHelp = $this->multitasking_model->getAllUsersForHelp($task_id, $post);
                            $getTelegramIdForHelpWhoInsert = $this->multitasking_model->getAllUsersForHelpInsert($task_id, $post);
                            $getResponsNoti = $this->multitasking_model->getResponForNoti($task_id);
                            $count = count($getTelegramId);
                            $count_res = count($getTelegramIdForHelp);
                            $count_insert = count($getTelegramIdForHelpWhoInsert);
                            $status_task = 0;
                            $type_task_push = 0;

                            $current_status_task = $getTaskForNotification[0]->multitask_status;

                            // Создаем строку сотрудников, которых пригласили
                            $newHelpEmployee = '';
                            for ($i = 0; $i < $count; $i++) {
                                if($i == $count-1){
                                    $newHelpEmployee .= $getTelegramId[$i][0]->surname." ".$getTelegramId[$i][0]->name." ".$getTelegramId[$i][0]->middlename;
                                } else {
                                    $newHelpEmployee .= $getTelegramId[$i][0]->surname." ".$getTelegramId[$i][0]->name." ".$getTelegramId[$i][0]->middlename."\n";
                                }
                            }


                            // Отправляем уведомление новым пользователям задачи
                            if(!empty($getTelegramId)){
                                if(empty($getTaskForNotification[0]->points_street) && empty($getTaskForNotification[0]->points_building)){
                                    $telegram_text = urlencode("<b>".$getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." добавил(а) вас в задачу!</b>\nНомер задачи - " . $getTaskForNotification[0]->multitask_id . "\nОписание - " . $this->wrapperText($getTaskForNotification[0]->multitask_full) . "\nДата назначения - " .  date("d.m.Y H:i", strtotime($getTaskForNotification[0]->multitask_date)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($getTaskForNotification[0]->multitask_date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($getTaskForNotification[0]->multitask_date_period)) . "\nИсполнители:\n".$getResponsNoti);
                                } else {
                                    $telegram_text = urlencode("<b>".$getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." добавил(а) вас в задачу!</b>\nНомер задачи - " . $getTaskForNotification[0]->multitask_id . "\nОписание - " . $this->wrapperText($getTaskForNotification[0]->multitask_full) . "\nОбъект - ".$getTaskForNotification[0]->points_street." ".$getTaskForNotification[0]->points_building."\nДата назначения - " .  date("d.m.Y H:i", strtotime($getTaskForNotification[0]->multitask_date)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($getTaskForNotification[0]->multitask_date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($getTaskForNotification[0]->multitask_date_period)) . "\nИсполнители:\n".$getResponsNoti);
                                }
                                $type_task_for_user = 0;
                                for ($i = 0; $i < $count; $i++) {
                                    $this->traits_library->connectAndSendPush($getTelegramId[$i][0]->telegram_id, $telegram_text, $getTaskForNotification[0]->multitask_id, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);
                                }
                            }

                            // Отправляем пользователям, которые уже есть в задании (только исполнителям)
                            if(empty($getTaskForNotification[0]->points_street) && empty($getTaskForNotification[0]->points_building)){
                                $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." добавил(а) в задачу:\n<b>".$newHelpEmployee."</b>\nНомер задачи - " . $getTaskForNotification[0]->multitask_id . "\nОписание - " . $this->wrapperText($getTaskForNotification[0]->multitask_full) . "\nДата назначения - " .  date("d.m.Y H:i", strtotime($getTaskForNotification[0]->multitask_date)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($getTaskForNotification[0]->multitask_date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($getTaskForNotification[0]->multitask_date_period)) . "\n Исполнители:\n". $getResponsNoti);
                            } else {
                                $telegram_text = urlencode($getEmployeeCook[0]->surname." ".$getEmployeeCook[0]->name." ".$getEmployeeCook[0]->middlename." добавил(а) в задачу:\n<b>".$newHelpEmployee."</b>\nНомер задачи - " . $getTaskForNotification[0]->multitask_id . "\nОписание - " . $this->wrapperText($getTaskForNotification[0]->multitask_full) . "\nОбъект - ".$getTaskForNotification[0]->points_street." ".$getTaskForNotification[0]->points_building."\nДата назначения - " .  date("d.m.Y H:i", strtotime($getTaskForNotification[0]->multitask_date)) . "\nДата начала выполнения - " . date("d.m.Y H:i", strtotime($getTaskForNotification[0]->multitask_date_begin)) . "\nДата исполнения - " . date("d.m.Y H:i:s", strtotime($getTaskForNotification[0]->multitask_date_period)) . "\nИсполнители:\n".$getResponsNoti);
                            }
                            for($j = 0; $j < $count_res; $j++){
                                $type_task_for_user = 0;
                                if (!empty($getTelegramIdForHelp[$j]->telegram_id)) {
                                    if($getTelegramIdForHelp[$j]->user_id != USER_COOKIE_ID){
                                        $this->traits_library->connectAndSendPush($getTelegramIdForHelp[$j]->telegram_id, $telegram_text, $getTaskForNotification[0]->multitask_id, $status_task, $type_task_push, $type_task_for_user, 0, 0, 0, $current_status_task);
                                    }
                                }
                            }

                            //Отправляем уведомление инициатору задания(если он пригласил на помощь, то уведомление не отпраляем)
                            for($y = 0; $y < $count_insert; $y++){
                                $type_task_for_user = 1;
                                if (!empty($getTelegramIdForHelpWhoInsert[$y]->telegram_id)) {
                                    if($getTelegramIdForHelpWhoInsert[$y]->user_id != USER_COOKIE_ID){
                                        $this->traits_library->connectAndSendPush($getTelegramIdForHelpWhoInsert[$y]->telegram_id, $telegram_text, $getTaskForNotification[0]->multitask_id, $status_task, $type_task_push, $type_task_for_user, 1, 0, 0, $current_status_task);
                                    }
                                }
                            }
                            //echo "<pre>";print_r($newHelpEmployee);exit;
                            echo json_encode(array('status' => 'success'));
                        } else {
                            echo json_encode(array('status' => 'error'));
                        }
                    }
                }
            }
        } else {
            if (!is_numeric($task_id)) {
                show_404();
            } else {
                $this->task = \Entity\MacrotaskTask::find( $task_id );
                $data['performers'] = $this->task->getProperty('array_user_performers');

                if( !$this->permissions->checkPolicy('addPerformerInTask') ) {
                    show_404();
                } else {
                    $data['help_multitask'] = $this->multitasking_model->getTask($task_id);
                    if (isset($data['help_multitask'][0]->multitask_id)) {
                        $data['employee'] = $this->users_model->getEmployed();
                        $taskMakers = $this->multitasking_model->getForTasksEmployee($task_id);
                        foreach ($taskMakers as $taskMaker) {
                            $data['taskMakerIds'][] = $taskMaker->user_id;
                        }
                        $data['multitask'] = 'multitask';
                        $data['title'] = 'Позвать на помощь';
                        $this->view_library->allViewLibAndQuery('multitask', 'help', $data);
                    } else {
                        show_404();
                    }
                }
            }
        }
    }

    private function changeEmployeeTasksREsp(){
        $this->multitasking_model->changeRespTaskEmployee();
    }

    public function who_insert(){
        $post['task_id'] = htmlspecialchars(strip_tags($_POST['task_id']));
        $post['user_id'] = htmlspecialchars(strip_tags($_POST['user_id']));
        $result = $this->multitasking_model->getWhoInsertAllDepa($post);
        if (!empty($result)) {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    /*
     * Не использовать
     * */
    private function getAndInsertWho(){
        $this->multitasking_model->getWhoInsert();
    }

    /*
     * Получение имен сотрудников
     * */
    public function getNameEmployee(){
        $this->multitasking_model->getNameEmployeeBd();
    }

    public function getAllEmployee(){
        $employee = $this->users_model->getAll();
        if (!empty($employee)) {
            echo json_encode(array('status' => 'success', 'result' => $employee));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function analytLink(){
        //WorkInMultitask::clearCookies();
        $this->traits_library->clearCookies();
        $post['status'] = $this->secur_library->addPost($_GET['type'], 1);
        //print_r($post['status']);exit;
        if (isset($post['status'])) {
            $this->set_cookies_library->setCookies('load', $post['status'], '2628000');
        }
        $save['parament'] = (object)$post;
        if (isset($_COOKIE['load'])) {
            $load['load'] = (int)$_COOKIE['load'];
        } else {
            $load['load'] = 0;
        }
        $result = $this->multitasking_model->getTasksAllUsers($post, $load);
        redirect('/multitasking/all_tasks');
    }

    public function transeftTasksEmployee(){
        //echo "<pre>";print_r($_POST);exit;
        $post['user_id'] = htmlspecialchars(strip_tags($_POST['employee']));
        $array['task_id'] = $_POST['task_id'];
        $result = $this->multitasking_model->transderTasksEmployee($post, $array);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function loadPage(){
        $load = $this->secur_library->addPost($_POST['count_load'], 1);
        $this->set_cookies_library->setCookies('count_load', $load, '94670778');
        echo json_encode(array('status' => 'success'));
    }

    public function addElect(){
        $post['task_id'] = $this->secur_library->addPost($_POST['task_id'], 1);
        $post['user_id'] = USER_COOKIE_ID;
        $post['date'] = date('Y-m-d H:i:s');
        $result = $this->multitasking_model->addElectForUsers($post);
        if ($result == 'true') {
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function lightVerseion(){
        if ($_POST['radio'] == 'true') {
            $value = $this->secur_library->addPost($_POST['value'], 1);
            $this->set_cookies_library->setCookies('light', $value, '94670778');
            delete_cookie('count_load');
            $value = 25;
            $this->set_cookies_library->setCookies('count_load', $value, '94670778');
        } elseif ($_POST['radio'] == 'false') {
            delete_cookie('light');
        }
        echo json_encode(array('status' => 'success'));
    }

    private function getStatusTask(){
        $result = $this->multitasking_model->getAllStatusTtask();
        //echo "<pre>";print_r($result);
        foreach($result as $res) {
            $arr = "UPDATE multitask SET status = 3  WHERE id = ".$res['id'].";";
            echo "<pre>";print_r($arr);
        }
        //upo($arr);
        exit;
    }

    private function getPeriod(){
        $result = $this->multitasking_model->getAllTasksPeriod();
        foreach($result as $res) {
            $arr = "INSERT INTO update_tools (ksat_id, ksat_t)
                            VALUES ('".md5($res->id)."', '".md5($res->date_period."_".$res->id."_strip_tags")."');";
            echo "<pre>";print_r($arr);
        }
        //exit('Good');
    }

    public function loadAjaxT(){
        $load['load'] = $_POST['load'];
        if (isset($_COOKIE['department'])) {
            $post['department'] = $_COOKIE['department'];
        } else {
            $post['department'] = null;
        }
        if (isset($_COOKIE['employee'])) {
            $post['employee'] = $_COOKIE['employee'];
        } else {
            $post['employee'] = null;
        }
        if (isset($_COOKIE['status'])) {
            $post['status'] = $_COOKIE['status'];
        } else {
            $post['status'] = null;
        }
        if (isset($_COOKIE['executor'])) {
            $post['executor'] = $_COOKIE['executor'];
        } else {
            $post['executor'] = null;
        }
        if (isset($_COOKIE['komentator'])) {
            $post['komentator'] = $_COOKIE['komentator'];
        } else {
            $post['komentator'] = null;
        }
        if (isset($_COOKIE['object'])) {
            $post['object'] = $_COOKIE['object'];
        } else {
            $post['object'] = null;
        }
        if (isset($_COOKIE['brand'])) {
            $post['brand'] = $_COOKIE['brand'];
        } else {
            $post['brand'] = null;
        }
        if (isset($_COOKIE['priority'])) {
            $post['priority'] = $_COOKIE['priority'];
        } else {
            $post['priority'] = null;
        }
        if (isset($_COOKIE['from'])) {
            $post['from'] = date("Y-m-d", strtotime($_COOKIE['from']));
        } else {
            $post['from'] = null;
        }
        if (isset($_COOKIE['to'])) {
            $post['to'] = date("Y-m-d", strtotime($_COOKIE['to']));;
        } else {
            $post['to'] = null;
        }
        if (isset($_COOKIE['search'])) {
            $post['search'] = $_COOKIE['search'];
        } else {
            $post['search'] = null;
        }
        $result = $this->multitasking_model->getTasksAllUsers($post, $load);
        if(!empty($result)){
            echo json_encode(array('status' => 'success', 'value' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function updateVoice(){
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        $data['voice_t'] = 1;
        $data['exit_t'] = 2;
        $result = $this->multitasking_model->updateVoiceBd($task_id, USER_COOKIE_ID, $data);
        if($result == 'true'){
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function usersVoted(){
        //echo "<pre>";print_r($_POST);exit;
        $task_id = $this->secur_library->addPost($_POST['task_id'], 1);
        $data['voice_t'] = 1;
        $this->multitasking_model->updateVoiceBd($task_id, USER_COOKIE_ID, $data);
        $result = $this->multitasking_model->updateVotedUsers($task_id, USER_COOKIE_ID);
        if($result == 'true'){
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function viewTaskCompleted(){
        $data['title'] = 'Задачи';
        $data['department'] = $this->employee_model->getDepartment();
        $data['employee'] = $this->users_model->getAll();
        $data['all_objects'] = $this->multitasking_model->getAllObjects();
        //$data['multitask'] = 'multitask';
        $data['multitask_add'] = 'multitask_add';
        $data['completedMultitask'] = $this->multitasking_model->getWhoInsertDone(USER_COOKIE_ID);
        $this->view_library->allViewLibAndQuery('multitask', 'completed', $data);
    }

    public function updateStatusTaskDirector(){
        //echo "<pre>";print_r($_POST['em_id']);exit;
        $result = $this->multitasking_model->updateStatusCLoseDirecorBD($_POST['em_id']);
        if($result == 'true'){
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function getTorg(){
        $result = $this->multitasking_model->getAllObjects();
        if(!empty($result)){
            echo json_encode(array('status' => 'success', 'result' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function num2word($num, $words){
        $num = $num % 100;
        if ($num > 19) {
            $num = $num % 10;
        }
        switch ($num) {
            case 1: {
                return($words[0]);
            }
            case 2: case 3: case 4: {
            return($words[1]);
        }
            default: {
                return($words[2]);
            }
        }
    }

    public function addQuickComm(){
        $post['user_id'] = USER_COOKIE_ID;
        $post['name'] = $this->secur_library->addPost($_POST['name'], 0);
        $post['date'] = date('Y-m-d H:i:s');
        $result = $this->multitasking_model->addQuickCommBd($post, USER_COOKIE_ID);
        if(!empty($result)){
            echo json_encode(array('status' => 'success', 'result' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function deleteQuuickComm($id = null){
        $this->multitasking_model->deleteQuuickCommBd($id);
        echo json_encode(array('status' => 'success'));
    }

    function is_urla($string){
        $findme   = 'http://';
        $findmeHttps   = 'https://';
        $pos = stristr($string, $findme);
        $poss = stristr($string, $findmeHttps);

        if ($pos == false && $poss == false) {
        } else {
            $newStr = explode(' ',$string);
            $count = count($newStr);
            $newLink = array();
            for($i = 0; $i < $count; $i++){
                $testrr = stristr($newStr[$i], $findme);
                $testrrHttps = stristr($newStr[$i], $findmeHttps);
                if(!empty($testrr)){
                    array_push($newLink, $testrr);
                } else if(!empty($testrrHttps)){
                    array_push($newLink, $testrrHttps);
                }
            }
            $new_text = preg_replace("/(^|[\n ])([\w]*?)((ht|f)tp(s)?:\/\/[\w]+[^ \,\"\n\r\t<]*)/is", "$1$2<a style='word-wrap: break-word' target='_blank' href=\"$3\" >$3</a>", $string);
            return $new_text;
            echo "<pre>";print_r($new_text);echo "</pre>";
        }
    }
}
