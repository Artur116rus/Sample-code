<?php
defined('BASEPATH') OR exit('No direct script access allowed');

interface multitMethod{
    public function index();
    public function add();
    public function search();
    public function edit($task_id);
    public function delete();
    public function review($task_id);
    public function updateStatusWhoInsert($task_id);
    public function addComment($task_id);
    public function addCommentApi();
    public function comment_answer();
    public function all_tasks();
    public function sortTask();
    public function declaim();
    public function createPrintUsers();
    public function createPrintMyTasks();
    public function createPrintGroupUsers();
    public function createPrintDepartment();
    public function createPrintReportEmployee();
    public function sendCommentOYKD();
    public function closeTask();
    public function file_force_download($file);
    public function searchTasks();
    public function searchMyTasks();
    public function deleteTaskAdmin();
    public function resstablishTask();
    public function viewReminderTask();
    public function viewCommentNotification();
    public function viewAllNotification();
    public function closeDirector();
    public function selectEmployeeForOykd();
    public function selectTasksForOykdCash();
    public function actionOverMyTasks();
    public function closeTaskUser();
    public function addRemidersForEmployeesNoOykd();
    public function selectExecutor();
    public function selectExecutorMyTask();
    public function selectExecutorAllTask();
    public function selectObjectForSortTasks();
    public function selectTasksForCloseOYKD();
    public function selectbyBrand();
    public function dateReminder();
    public function getNewSelectAllTasks();
    public function deleteCashUser();
    public function recall($task_id);
    public function writeUser();
    public function setPage();
    public function sortMyTasks();
    public function viewCommentForTask();
    public function viewCommentForTaskAnswer();
    public function helpTask($task_id);
    public function who_insert();
    public function getNameEmployee();
    public function getAllEmployee();
    public function analytLink();
    public function transeftTasksEmployee();
    public function addJalob();
    public function loadPage();
    public function lightVerseion();
}


trait ConnectPush{
    static function connectAndSendPush($id, $telegram_text){
        $ch = curl_init('https://api.telegram.org/bot'.TELEGRAM_TOKEN.'/sendMessage?chat_id=' . $id . '&text=' . $telegram_text . '');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_exec($ch);
        curl_close($ch);
    }
}

trait WorkInMultitask{
    static function setNameUrl(){
        if($_SERVER['SERVER_NAME'] == SERVER_NAME_CONST){
            $url = "http://".SERVER_NAME_CONST."/";
        } elseif($_SERVER['SERVER_NAME'] == SERVER_NAME_CONST_BOI){
            $url = "https://".SERVER_NAME_CONST."/";
        }
        return $url;
    }

    static function clearCookies(){
        $CI =& get_instance();
        delete_cookie('load', null, "/multitasking");
        delete_cookie('load');
        delete_cookie('department');
        delete_cookie('employee');
        delete_cookie('status');
        delete_cookie('executor');
        delete_cookie('object');
        delete_cookie('brand');
        delete_cookie('priority');
        $CI->set_cookies_library->setCookies('load', 0, '2628000');
    }
}

class Multitasking extends CI_Controller implements multitMethod{

    use ConnectPush, WorkInMultitask;

    public function __construct(){
        parent::__construct();
        $this->check_user_library->checkUser();
        $this->load->model('permission_model');
        $this->load->model('employee_model');
        $this->load->model('multitasking_model');
        $this->load->model('modules_model');
    }

    public function index(){
        /*
         * Выборка по табам в разделе "Мои задания"
         *
         * */

        $time_start = microtime(true);

        $data['multitasks'] = $this->multitasking_model->getTasksActive(USER_COOKIE_ID);
        $data['done_multitask'] = $this->multitasking_model->getDonetask(USER_COOKIE_ID);
        $data['closemultitasks'] = $this->multitasking_model->getTasksClose(USER_COOKIE_ID);
        if (isset($_COOKIE['save_result']) && !empty($_COOKIE['save_result'])) {
            $post['user_id'] = $_COOKIE['save_result'];
            $data['taskWhoInsert'] = $this->multitasking_model->getSelectExecutor($post, USER_COOKIE_ID);
        } else {
            $data['taskWhoInsert'] = $this->multitasking_model->getWhoInsertActive(USER_COOKIE_ID);
        }

        $data['taskWhoInsertDone'] = $this->multitasking_model->getWhoInsertDone(USER_COOKIE_ID);
        $data['taskWhoInsertClose'] = $this->multitasking_model->getWhoInsertClose(USER_COOKIE_ID);
        $data['taskMyAll'] = $this->multitasking_model->getTaskMyAll(USER_COOKIE_ID);
        /*
         * Конец выборки по табам в разделе "Мои задания"
         *
         * */

        /*
       * Count В моих заданиях
       * */
        $data['countTasksActive'] = $this->multitasking_model->getTasksActiveCount(USER_COOKIE_ID);
        $data['countTasksDone'] = $this->multitasking_model->getDonetaskCount(USER_COOKIE_ID);
        $data['countTasksClose'] = $this->multitasking_model->getTasksCloseCount(USER_COOKIE_ID);
        $data['taskWhoInsertCount'] = $this->multitasking_model->getWhoInsertActiveCount(USER_COOKIE_ID);
        $data['taskWhoInsertCloseCount'] = $this->multitasking_model->getWhoInsertCloseCount(USER_COOKIE_ID);
        $data['countInsertTasksDone'] = $this->multitasking_model->getInsertDonetaskCount(USER_COOKIE_ID);
        $data['taskMyAllCount'] = $this->multitasking_model->getTaskMyAllCount(USER_COOKIE_ID);
        /*
         * Конец Count
         * */

        /*
         * Выборка для Дениса(it-отдел, 1с-ники(они входят в it-отдел))
         *
         * */
        if (isset($_COOKIE['taskItOtdel'])) {
            $allTaskItDep = $_COOKIE['taskItOtdel'];
        } else {
            $allTaskItDep = '';
        }
        $data['getTasksItDepartment'] = $this->multitasking_model->getTasksItDepartment($allTaskItDep); // Выборка it-отдела
        $data['getTasksItDepartmentCount'] = $this->multitasking_model->getTasksItDepartmentCount($allTaskItDep); // Количество задач it-отдела

        /*
         *
         * Конец выборки it-отдела
         *
         * */

        $data['title'] = 'Задания';
        $data['user_session'] = $this->employee_model->getSesionUser(USER_COOKIE_ID);
        $data['user_cookie_id'] = USER_COOKIE_ID;
        $data['view_tasks_my_department'] = $this->permission_model->viewMyDepartment(USER_COOKIE_ID);

        //print_r($data['view_tasks_my_department']);
        if (isset($data['view_tasks_my_department'][0]->department_id)) {
            $department_id = $data['view_tasks_my_department'][0]->department_id;
        } else {
            $department_id = 1;
        }
        $data['tasks_my_department'] = $this->multitasking_model->getTasksMyDepartment($department_id);
        $data['tasks_my_departmentCount'] = $this->multitasking_model->getTasksMyDepartmentCount($department_id);
        // Данные для печати
        $data['print_surname'] = $data['user_session'][0]->surname;
        $data['print_name'] = $data['user_session'][0]->name;
        $data['print_middle_name'] = $data['user_session'][0]->middlename;

        $data['employee'] = $this->multitasking_model->getUsers();
        // Конец Данные для печати
        $data['multitask'] = 'multitask';
        $data['multitask_my'] = 'multitask_my';
        $this->view_library->allViewLibAndQuery('multitask', 'multitask', $data);

        $time_end = microtime(true);
        $time = $time_end - $time_start;
        //print_r($time);exit;
    }

    public function add(){
        if (isset($_POST) && sizeof($_POST) > 0) {
            //echo "<pre>";print_r($_POST);exit;
            if (!empty($_FILES)) {
                $name = 'document';
                $name_folder = './assets/tasks_documents/';
                $post['image'] = $this->download_library->saveFileServer($name, $name_folder);
            } else {
                $post['image'] = '';
            }
            //print_r(USER_COOKIE_ID);exit;
            $getDepartment = $this->multitasking_model->getDepartmentUser(USER_COOKIE_ID);
            if ($getDepartment[0]->active == 0) {
                $post['assepted'] = 2;
            }
            $new_time = $_POST['date'];
            $new_time .= $_POST['date_time'];

            if (isset($_POST['select_object'])) {
                $post['points_id'] = $this->secur_library->addPost($_POST['select_object'], 1);
            } else {
                $post['points_id'] = 0;
            }

            $titme_begin = $_POST['date_begin'];
            $titme_begin .= $_POST['date_time_begin'];
            $post['date_begin'] = date("Y-m-d H:i:s", strtotime($titme_begin));

            $post['full'] = $this->secur_library->addPost($_POST['full'], 0);
            $post['date'] = date("Y-m-d H:i:s");
            $post['date_period'] = date("Y-m-d H:i:s", strtotime($new_time));
            $post['status'] = STATUS_TASK_1;
            $post['priority'] = $this->secur_library->addPost($_POST['priority'], 1);
            //echo "<pre>";print_r($post);exit;
            if (isset($_POST['iniciator']) && !empty($_POST['iniciator'])) {
                $post['select_responsible'] = (int)get_cookie('id', TRUE);
                $select_responsible = $this->secur_library->addPost($_POST['iniciator'], 1);
            } else {
                $select_responsible = '';
            }
            $array['responsible'] = $_POST['responsible'];
            $result = $this->multitasking_model->add($post, $array, $select_responsible);
            $getTaskForNotification = $this->multitasking_model->getTaskForNotification($result);
            //echo "<pre>";print_r($getTaskForNotification);exit;
            if (!empty($result)) {
                $textSms = "Добавлена задача!\r\nnew.buket116.ru/m/" . $getTaskForNotification[0]->multitask_id . "\r\nВыполнить до " . $getTaskForNotification[0]->date_period . "";
                $this->sms_library->sendSms($textSms, $getTaskForNotification);
                $pusher = $this->pusher_library->connect_push();
                $count = count($getTaskForNotification);
                $telegram_text = "Здравствуйте, вам была добавлено новое задание. Описание: " . $post['full'] . " Подробнее: ".WorkInMultitask::setNameUrl()."multitasking/review/" . $result;
                for ($i = 0; $i < $count; $i++) {
                    $new_task_noti = $this->modules_model->getNEwTask($array['responsible'][$i], 1);
                    if (!empty($new_task_noti)) {
                        $pusher->trigger('notifications_' . $array['responsible'][$i], 'tasks_notification', $getTaskForNotification);
                        if (!empty($getTaskForNotification[$i]->telegram_id)) {
                            ConnectPush::connectAndSendPush($getTaskForNotification[$i]->telegram_id,$telegram_text);
                        }
                    }
                    /*
                     * Отправка push уведомления на телефон(андроид)
                     * */
                    $text = $getTaskForNotification[0]->employee_surname." добавил задание №".$getTaskForNotification[0]->multitask_id." : ".$getTaskForNotification[0]->multitask_full;
                    $array_t = array(
                        'title' => "Новые задания",
                        'content' => $text,
                        'description' => 'У вас есть новые задания'
                    );
                    $json_t = json_encode($array_t);
                    $pusher->trigger('notifications_android_user_'.$array['responsible'][$i], 'notification_android', array('title' => "Новые задания", 'content' => $text, 'description' => 'У вас есть новые задания'));
                    /*
                     * Конец отправки
                     * */
                }
                echo json_encode(array('status' => 'success', 'responsible' => $array['responsible'], 'task_id' => $result));
            } else {
                echo json_encode(array('status' => 'error'));
            }
        } else {
            $data['title'] = 'Задания';
            $data['select_permission'] = $this->permissions_library->closeAccess('addNewTask');
            $data['department'] = $this->employee_model->getDepartment();
            $data['employee'] = $this->multitasking_model->getUsers();
            $data['all_objects'] = $this->multitasking_model->getAllObjects();
            //$data['multitask'] = 'multitask';
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

    /*
     * Редактирование задачи
     *
     * */
    public function edit($task_id = null){
        if (!is_numeric($task_id)) { // проверка на число
            show_404();
        }
        if (isset($_POST) && sizeof($_POST) > 0) { // Проверяем пришел ли POST
            if(isset($_POST['date_begin']) || isset($_POST['date_time_begin']) || isset($_POST['date']) || isset($_POST['date_time'])) {
                //echo "<pre>";print_r($_POST);exit;
                $hashForUp = md5(USER_COOKIE_ID);
                //print_r($hashForUp);exit;
                $this->multitasking_model->dPFNU(USER_COOKIE_ID, $hashForUp);
                echo json_encode(array('status' => 'ban'));exit;
            }
            //echo "<pre>";print_r($_POST);exit;
            //echo "<pre>";print_r("vse morm");exit;
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
            /*$time = explode('T', $_POST['date']);
            $new_time = $time[0].' '.$time[1];*/
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
            //echo "<pre>";print_r(date("d-m-y H:i", strtotime($new_time)));exit;
            $post['full'] = $this->secur_library->addPost($_POST['full'], 0);
            if (isset($_POST['short'])) {
                $post['status'] = $_POST['status'];
            }
            if (isset($_POST['select_resp']) && !empty($_POST['select_resp'])) {
                $new_respons['who_insert_id'] = $this->secur_library->addPost($_POST['select_resp'], 1);
                //echo "<pre>";print_r( $new_respons['who_insert_id']);exit;
                $changeIniciator = $this->multitasking_model->changeInic($new_respons, $task_id);
            } else {
                $new_respons['who_insert_id'] = '';
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

            $edit_multitask = $this->multitasking_model->getTask($task_id);
            //echo "<pre>";print_r($arr);exit();
            //echo "<pre>";print_r($edit_multitask);exit;
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
            //echo "<pre>";print_r($log);exit;
            if (isset($log)) {
                $log = serialize($log);
            } else {
                $log = null;
            }
            //echo "<pre>";print_r($new_respons);exit;
            $result = $this->multitasking_model->editTask($post, $task_id, $arr, $log, $new_respons);
            $edit_multitask = $this->multitasking_model->getForTasksEmployee($task_id);
            //echo "<pre>";print_r($edit_multitask[0]->phone);exit;
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

                echo json_encode(array('status' => 'success'));
            } else {
                echo json_encode(array('status' => 'error'));
            }
        } else {
            $hashUser = $this->multitasking_model->getHashUser(USER_COOKIE_ID);
            if(empty($hashUser)) {
                $getDepartment = $this->multitasking_model->getDepartmentUser(USER_COOKIE_ID);
                if ($getDepartment[0]->dep_id != DEPARTMENT_ID_3) {
                    $checkPermEdit = $this->multitasking_model->checkPermEdit($task_id, USER_COOKIE_ID);
                    if (empty($checkPermEdit)) {
                        show_404();
                    }
                }
                $data['title'] = 'Задания';
                $data['department'] = $this->employee_model->getDepartment();
                $data['employee'] = $this->multitasking_model->getUsersArray();
                $data['review_multitask_employee'] = $this->multitasking_model->getForTasksEmployeeEdit($task_id);
                $data['edit_multitask'] = $this->multitasking_model->getTask($task_id);
                $data['all_objects'] = $this->multitasking_model->getAllObjects();
                $data['get_edit_objects'] = $this->multitasking_model->getEditObjects($task_id);
                $data['multitask'] = 'multitask';
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
            //echo "<pre>";print_r($_POST);exit;
            $this->multitasking_model->reviewTasksLog($task_id, USER_COOKIE_ID);
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
            $data['review_multitask'] = $this->multitasking_model->getTask($task_id);
            if (isset($data['review_multitask'][0]->select_responsible) && !empty($data['review_multitask'][0]->select_responsible)) {
                $data['review_multitask_curator'] = $this->multitasking_model->getTaskCurator($task_id);
            }
            $data['review_multitask_for_type_task'] = $this->multitasking_model->getTaskFOrTypeTask($task_id);
            $data['review_multitask_close_user'] = $this->multitasking_model->getTaskCloseUser($task_id);
            $data['review_multitask_employee'] = $this->multitasking_model->getForTasksEmployee($task_id);
            //echo "<pre>";print_r($data['review_multitask_employee']);exit;
            $data['review_multitask_who_insert'] = $this->multitasking_model->getWhoInsertTask($task_id);
            //echo "<pre>";print_r($data['review_multitask_who_insert']);exit;
            if (empty($data['review_multitask'])) {
                show_404();
            } else {
                //exit('qwdqw');
                // Закрываем доступ для лишних глаз
                $data['checkMyTasks'] = $this->multitasking_model->checkViewTaskMy($task_id, USER_COOKIE_ID);
                $checkOykd = $this->multitasking_model->getDepartmentUser(USER_COOKIE_ID);
                $checkWhoInsert = $this->multitasking_model->checkViewTaskWhoInsert($task_id, USER_COOKIE_ID);

                $data['view_tasks_my_department'] = $this->permission_model->viewMyDepartment(USER_COOKIE_ID);
                //print_r($data['view_tasks_my_department']);
                if (isset($data['view_tasks_my_department'][0]->department_id)) {
                    $department_id = $data['view_tasks_my_department'][0]->department_id;
                } else {
                    $department_id = 1;
                }
                $tasks_my_department = $this->multitasking_model->getTaskIdDepartment($task_id, $department_id);

                //echo "<pre>";print_r($checkOykd);exit;
                if (USER_COOKIE_ID != DIRECTOR_ID && USER_COOKIE_ID != ELENA_ID && USER_COOKIE_ID != ATLASOV_ID && USER_COOKIE_ID != 27) { // Для этого можно создать отдельную таблицу в БД
                    if (!empty($data['checkMyTasks'])) {
                        if ($data['checkMyTasks'][0]->task_id != $task_id) {
                            show_404();
                        }
                    } elseif (!empty($checkWhoInsert)) {
                        if ($checkWhoInsert[0]->task_id != $task_id) {
                            show_404();
                        }
                    } elseif ($checkOykd[0]->dep_id != DEPARTMENT_ID_3 && empty($tasks_my_department)) {
                        show_404();
                    }
                }
                //echo "<pre>";print_r($data['review_multitask']);exit;
                $data['user_id_session'] = get_cookie('id', TRUE);
                $data['close_tasks_director'] = $this->permission_model->closeTasksDirectorPermisions(USER_COOKIE_ID);

                $data['reviewTaskLogs'] = $this->multitasking_model->getReviewTaskLogs($task_id);
                //$data['edit_tasks'] = $this->permission_model->getPermissionEditTask(USER_COOKIE_ID);
                //echo "<pre>";print_r($data['edit_tasks']);exit;

                $data['spoki_noki'] = $this->multitasking_model->getLogSpokiNoki($task_id);
                $data['help_log_task'] = $this->multitasking_model->getLogHelpTask($task_id);

                $data['hashUser'] = $this->multitasking_model->getHashUser(USER_COOKIE_ID);

                $data['allNotesForTask'] = $this->multitasking_model->getallNotesForTask($task_id);
                $data['comment_oykd'] = $this->multitasking_model->getCommentOykd($task_id);
                $data['all_tasks'] = $this->multitasking_model->getTasks($task_id);
                $data['logsTask'] = $this->multitasking_model->getLogsTask($task_id);
                $data['getRecall'] = $this->multitasking_model->getRecall($task_id, USER_COOKIE_ID);
                //echo "<pre>";print_r(unserialize($data['logsTask'][1]->log));exit;
                //echo "<pre>";print_r($data['logsTask']);exit;
                $data['task_id'] = $task_id;
                //$data['multitask'] = 'multitask';
                $data['title'] = 'Просмотр задания №' . $task_id;
                $data['multitask_review'] = 'multitask_review';
                $this->view_library->allViewLibAndQuery('multitask', 'review', $data);
            }
        }
    }

    public function updateStatusWhoInsert($task_id = null){
        if (!is_numeric($task_id)) {
            show_404();
        } else {
            $this->multitasking_model->reviewTasksLog($task_id, USER_COOKIE_ID);
            $status_change_text['change_content_out'] = 0;
            $this->multitasking_model->updateChangeContentWhoInsert($task_id, $status_change_text, USER_COOKIE_ID);
            echo json_encode(array('status' => 'success'));
        }
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
        if (!empty($_FILES)) {
            $count = count($_FILES);
            //print_r($count);exit;
            for ($i = 0; $i < $count; $i++) {
                $filename = $_FILES['document_' . $i . '']['name'];  // Получаем изображение
                //echo "<pre>";print_r($filename);exit;
                $type = $_FILES['document_' . $i . '']['type'];
                $size = $_FILES['document_' . $i . '']['size'];
                if ($size > MAX_SIZE_PICTURE) {
                    echo json_encode(array('status' => 'error_size')); // Размер картинки или файла превышает 8 Мегабайт
                    exit;
                } elseif ($type != 'image/jpeg' && $type != 'image/png' && $type != 'image/jpg' && $type != 'text/plain' && $type != 'audio/mp3' && $type != 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' && $type != 'application/msword' && $type != 'audio/mp3' && $type != 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' && $type != 'application/pdf' && $type != 'application/vnd.ms-excel') {
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
        //echo "<pre>";print_r($respons_task_noti);echo "</pre>";
        //echo "<pre>";print_r($iniciator_task_spoki);exit;
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

            $telegram_text = "У вас новый комментарий! Описание: " . substr($post['text'], 0, 30) . ". Более подробнее: ".WorkInMultitask::setNameUrl()."multitasking/review/" . $task_id;
            if (!empty($respons_task_noti)) {
                foreach ($respons_task_noti as $noti) {
                    $new_comment_noti = $this->modules_model->getNEwTask($noti['user_id'], 2);
                    if (!empty($new_comment_noti)) {
                        $pusher->trigger('notifications_' . $noti['user_id'], 'comment_task_notification', $result[0]);
                        if (!empty($noti['telegram_id'])) {
                            ConnectPush::connectAndSendPush($noti['telegram_id'],$telegram_text);
                        }
                    }
                }
            }

            if (!empty($iniciator_task_spoki)) {
                $new_comment_noti = $this->modules_model->getNEwTask($iniciator_task_spoki[0]['user_id'], 2);
                if (!empty($new_comment_noti)) {
                    $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'comment_task_notification', $result[0]);
                    if (!empty($iniciator_task_spoki[0]['telegram_id'])) {
                        ConnectPush::connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text);
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

            $telegram_text = "У вас новый комментарий! Описание: " . $post['text'] . ". Более подробнее: ".WorkInMultitask::setNameUrl()."multitasking/review/" . $task_id;
            if (!empty($respons_task_noti)) {
                foreach ($respons_task_noti as $noti) {
                    $new_comment_noti = $this->modules_model->getNEwTask($noti['user_id'], 2);
                    if (!empty($new_comment_noti)) {
                        $pusher->trigger('notifications_' . $noti['user_id'], 'comment_task_notification', $result[0]);
                        if (!empty($noti['telegram_id'])) {
                            ConnectPush::connectAndSendPush($noti['telegram_id'],$telegram_text);
                        }
                    }
                }
            }

            if (!empty($iniciator_task_spoki)) {
                $new_comment_noti = $this->modules_model->getNEwTask($iniciator_task_spoki[0]['user_id'], 2);
                if (!empty($new_comment_noti)) {
                    $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'comment_task_notification', $result[0]);
                    if (!empty($iniciator_task_spoki[0]['telegram_id'])) {
                        ConnectPush::connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text);
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
        $result = $this->multitasking_model->addCommentAnswer($post, $task_id);
        //echo "<pre>";print_r($result);exit;
        $respons_task_noti = $this->multitasking_model->getForTasksEmployeeNoti($task_id, USER_COOKIE_ID);
        $iniciator_task_spoki = $this->multitasking_model->getWhoInsertTaskNoti($task_id, USER_COOKIE_ID);

        if (!empty($result)) {
            $pusher = $this->pusher_library->connect_push();
            if (!empty($respons_task_noti)) {
                foreach ($respons_task_noti as $noti) {
                    $pusher->trigger('notifications_' . $noti['user_id'], 'comment_task_notification', $result[0]);
                }
            }
            if (!empty($iniciator_task_spoki)) {
                $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'comment_task_notification', $result[0]);
            }
            echo json_encode(array('status' => 'success', 'result' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function all_tasks(){
        $time_start = microtime(true);
        $data['title'] = 'Задания';
        //$data['multitask'] = 'multitask';
        $data['multitask_all'] = 'multitask_all';
        $data['select_department'] = $this->multitasking_model->getSelectAllDepartment();
        $data['delete_task_admin'] = $this->permission_model->deleteTasksPermisions(USER_COOKIE_ID);
        $data['objects_for_sort'] = $this->multitasking_model->getObjectsForSort();
        $data['brand'] = $this->multitasking_model->getBrand();
        $data['employee_sort'] = $this->multitasking_model->getUsers();
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
        //echo "<pre>";print_r($post);exit;
        if (isset($_COOKIE['load'])) {
            $load['load'] = (int)$_COOKIE['load'];
        } else {
            $load['load'] = 0;
        }

        $data['selectTasksEmployees']['result'] = $this->multitasking_model->getTasksAllUsers($post, $load);

        $data['count_tasks'] = count($data['selectTasksEmployees']['result']);
        $this->view_library->allViewLibAndQuery('multitask', 'all_tasks', $data);
        $time_end = microtime(true);
        $time = $time_end - $time_start;
        //echo "<br>";
        //print_r($time);exit;
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

    // Печать во вкладке "Мои задания"
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
        $task_id = $this->secur_library->addPost($_POST['task_id'], 0);
        $post['status'] = $this->secur_library->addPost($_POST['status'], 0);
        $post['date_close'] = date('Y-m-d H:i:s');

        $respons_task_noti = $this->multitasking_model->getForTasksEmployeeNoti($task_id, USER_COOKIE_ID);
        $iniciator_task_spoki = $this->multitasking_model->getWhoInsertTaskNoti($task_id, USER_COOKIE_ID);

        $result = $this->multitasking_model->updateStatusClose($post, $task_id);
        if ($result == 'true') {
            $telegram_text = "Задача была закрыта. Подробнее: ".WorkInMultitask::setNameUrl()."multitasking/review/" . $task_id;
            $pusher = $this->pusher_library->connect_push();
            if (!empty($respons_task_noti)) {
                foreach ($respons_task_noti as $noti) {
                    $pusher->trigger('notifications_' . $noti['user_id'], 'close_task_notification', array('task_id' => $task_id));
                    ConnectPush::connectAndSendPush($noti['telegram_id'],$telegram_text);
                }
            }

            if (!empty($iniciator_task_spoki)) {
                $new_close_task_noti = $this->modules_model->getNEwTask($iniciator_task_spoki[0]['user_id'], 3);
                if (!empty($new_close_task_noti)) {
                    $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'close_task_notification', array('task_id' => $task_id));
                    ConnectPush::connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text);
                }
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
        $this->multitasking_model->reviewTasksLog($task_id, USER_COOKIE_ID);
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
        // Если он отклоняет, то status = 1 and moder = 2
        // Если принимаем, то status = 5
        //echo "<pre>";print_r($_POST);
        $task_id = $_POST['task_id'];
        if ($_POST['status'] == 0) {
            $post['status'] = 1;
            $post['moder'] = 2;
        } else if ($_POST['status'] == 1) {
            $post['status'] = 5;
        }
        //print_r($post);
        $result = $this->multitasking_model->closeDirectorTasks($post, $task_id);
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
        //echo "<pre>";print_r($iniciator_task_spoki);exit;
        if (!empty($_POST['text'])) {
            $post['task_id'] = $task_id;
            $post['user_id'] = get_cookie('id', TRUE);
            $post['text'] = $this->secur_library->addPost($_POST['text'], 0);
            $post['files'] = '';
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
        $respons_task_noti = $this->multitasking_model->getForTasksEmployeeNoti($task_id, USER_COOKIE_ID);
        $iniciator_task_spoki = $this->multitasking_model->getWhoInsertTaskNoti($task_id, USER_COOKIE_ID);
        $user_id = USER_COOKIE_ID;
        $result = $this->multitasking_model->updateStatusCloseTaskUsers($task_id, $data, $post, $close_user, $user_id);
        //echo "<pre>";print_r($result);exit;

        $sendArray = array(
            'task_id' => $task_id,
            'status' => $this->secur_library->addPost($_POST['close_status'], 1)
        );
        if($sendArray['status'] == 1){
            $telegram_text = "Задача была обратно вам возвращена. Текст: ". substr($result[0]->full, 0, 30).", Номер задания: ". $result[0]->mult_id." Подробнее: ".WorkInMultitask::setNameUrl()."multitasking/review/" . $task_id;
        } else if($sendArray['status'] == 2){
            $telegram_text = "Задача была отправлена на закрытие. Текст: ". substr($result[0]->full, 0, 30).", Номер задания: ". $result[0]->mult_id." Подробнее: ".WorkInMultitask::setNameUrl()."multitasking/review/" . $task_id;
        }
        $pusher = $this->pusher_library->connect_push();
        if ($result == 'true') {
            if (!empty($respons_task_noti)) {
                foreach ($respons_task_noti as $noti) {
                    $new_reminder_noti = $this->modules_model->getNEwTask($noti['user_id'], 4);
                    if (!empty($new_reminder_noti)) {
                        $pusher->trigger('notifications_' . $noti['user_id'], 'send_close_task_notification', $sendArray);
                        ConnectPush::connectAndSendPush($noti['telegram_id'],$telegram_text);
                    }
                }
            }

            if (!empty($iniciator_task_spoki)) {
                $new_reminder_noti = $this->modules_model->getNEwTask($iniciator_task_spoki[0]['user_id'], 4);
                if (!empty($new_reminder_noti)) {
                    $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'send_close_task_notification', $sendArray);
                    ConnectPush::connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text);
                }
            }
            echo json_encode(array('status' => 'success', 'result' => $data['status']));
        } elseif (!empty($result)) {
            if($sendArray['status'] != 1){
                $pusher->trigger('test_channel_' . $task_id, 'comment_close_task', $result);
            }
            if (!empty($respons_task_noti)) {
                foreach ($respons_task_noti as $noti) {
                    $new_reminder_noti = $this->modules_model->getNEwTask($noti['user_id'], 4);
                    if (!empty($new_reminder_noti)) {
                        $pusher->trigger('notifications_' . $noti['user_id'], 'send_close_task_notification', $sendArray);
                        ConnectPush::connectAndSendPush($noti['telegram_id'],$telegram_text);
                    }
                }
            }

            if (!empty($iniciator_task_spoki)) {
                $new_reminder_noti = $this->modules_model->getNEwTask($iniciator_task_spoki[0]['user_id'], 4);
                if (!empty($new_reminder_noti)) {
                    $pusher->trigger('notifications_' . $iniciator_task_spoki[0]['user_id'], 'send_close_task_notification', $sendArray);
                    ConnectPush::connectAndSendPush($iniciator_task_spoki[0]['telegram_id'],$telegram_text);
                }
            }
            echo json_encode(array('status' => 'success', 'result' => 1));
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
           WorkInMultitask::clearCookies();
        }
        $post['department'] = $this->secur_library->addPost($_POST['department'], 1);
        $post['employee'] = $this->secur_library->addPost($_POST['employee'], 1);
        $post['status'] = $this->secur_library->addPost($_POST['status'], 1);
        $post['executor'] = $this->secur_library->addPost($_POST['executor'], 1);
        $post['object'] = $this->secur_library->addPost($_POST['object'], 1);
        $post['brand'] = $this->secur_library->addPost($_POST['brand'], 1);
        $post['priority'] = $this->secur_library->addPost($_POST['priority'], 1);
        //echo "<pre>";print_r($_POST);exit;

        if (!empty($post['department'])) {
            $this->set_cookies_library->setCookies('department', $post['department'], '2628000');
        }
        if (!empty($post['employee'])) {
            $this->set_cookies_library->setCookies('employee', $post['employee'], '2628000');
        }
        if (!empty($post['status'])) {
            $this->set_cookies_library->setCookies('status', $post['status'], '2628000');
        }
        if (!empty($post['executor'])) {
            $this->set_cookies_library->setCookies('executor', $post['executor'], '2628000');
        }
        if (!empty($post['object'])) {
            $this->set_cookies_library->setCookies('object',  $post['object'], '2628000');
        }
        if (!empty($post['brand'])) {
            $this->set_cookies_library->setCookies('brand',  $post['brand'], '2628000');
        }
        if (!empty($post['priority'])) {
            $this->set_cookies_library->setCookies('priority',  $post['priority'], '2628000');
        }

        if ($_POST['type'] == 'newSelect') {
            $this->set_cookies_library->setCookies('load',  0, '2628000');
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

        //print_r($load);exit;
        $result = $this->multitasking_model->getTasksAllUsers($post, $load);
        $save['parament'] = (object)$post;
        //echo "<pre>";print_r($save);exit;
        if (!empty($result)) {
            echo json_encode(array('status' => 'success', 'value' => $result));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function deleteCashUser(){
        WorkInMultitask::clearCookies();
        echo json_encode(array('status' => 'success'));
    }

    private function oxoxoAlalaUpdateClockTimeCron(){
        $this->multitasking_model->updateAllTasksActive();
    }

    private function unloadingExcelTasks(){
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
            ->setCellValue('B1', 'Исполнитель')
            ->setCellValue('C1', 'Описание')
            ->setCellValue('D1', 'Дата назначения')
            ->setCellValue('E1', 'Дата исполнения')
            ->setCellValue('F1', 'Дата одобрения')
            ->setCellValue('G1', 'Дата принята')
            ->setCellValue('H1', 'Дата закрытия')
            ->setCellValue('I1', 'Статус')
            ->setCellValue('J1', 'Модерация')
            ->setCellValue('K1', 'Приоритет');
        $result = $this->multitasking_model->getTaskExportToExcel();
        //echo "<pre>";print_r($result);exit;
        $count = count($result);
        for ($i = 0; $i < $count; $i++) {
            $j = $i + 2;
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A' . $j, $result[$i]->multitask_id)
                ->setCellValue('B' . $j, $result[$i]->employee_surname . ' ' . $result[$i]->employee_name . ' ' . $result[$i]->employee_middle_name)
                ->setCellValue('C' . $j, $result[$i]->multitask_full)
                ->setCellValue('D' . $j, $result[$i]->multitask_date)
                ->setCellValue('E' . $j, $result[$i]->multitask_date_period)
                ->setCellValue('F' . $j, $result[$i]->multitask_date_approved)
                ->setCellValue('G' . $j, $result[$i]->multitask_date_accepted)
                ->setCellValue('H' . $j, $result[$i]->multitask_date_close)
                ->setCellValue('I' . $j, $result[$i]->multitask_status)
                ->setCellValue('J' . $j, $result[$i]->multitask_moder)
                ->setCellValue('K' . $j, $result[$i]->multitask_priority);
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
        $this->multitasking_model->reviewTasksLog($task_id, USER_COOKIE_ID);
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
                $data['countTasksClose'] = $this->multitasking_model->getTasksCloseCount(USER_COOKIE_ID);
            } elseif ($page == 2) {
                $newpage = '#tab_1_3';
                $data['countTasksActive'] = $this->multitasking_model->getTasksActiveCount(USER_COOKIE_ID);
                $data['countTasksClose'] = $this->multitasking_model->getTasksCloseCount(USER_COOKIE_ID);
            } elseif ($page == 3) {
                $newpage = '#tab_1_5';
                $data['taskWhoInsertCount'] = $this->multitasking_model->getWhoInsertActiveCount(USER_COOKIE_ID);
                $data['taskWhoInsertCloseCount'] = $this->multitasking_model->getWhoInsertCloseCount(USER_COOKIE_ID);
            } elseif ($page == 4) {
                $newpage = '#tab_1_7';
                $data['taskWhoInsertCount'] = $this->multitasking_model->getWhoInsertActiveCount(USER_COOKIE_ID);
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
                $data['view_tasks_my_department'] = $this->permission_model->viewMyDepartment(USER_COOKIE_ID);
                $data['tasks_my_departmentCount'] = $this->multitasking_model->getTasksMyDepartmentCount($department_id);
            } elseif ($page == 7) {
                $newpage = '#tab_2_3';
                $data['getTasksItDepartment'] = $this->multitasking_model->getTasksItDepartment(); // Выборка it-отдела
                $data['getTasksItDepartmentCount'] = $this->multitasking_model->getTasksItDepartmentCount(); // Выборка it-отдела
            } elseif ($page == 8) {
                $newpage = '#tab_1_4';
                $data['done_multitask'] = $this->multitasking_model->getDonetask(USER_COOKIE_ID);
                $data['countTasksDone'] = $this->multitasking_model->getDonetaskCount(USER_COOKIE_ID);
            } elseif ($page == 9) {
                $newpage = '#tab_1_9';
                $data['taskWhoInsertDone'] = $this->multitasking_model->getWhoInsertDone(USER_COOKIE_ID);
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
        //echo "<pre>";print_r($sort);exit;
        $result = $this->multitasking_model->getTasksActiveSort(USER_COOKIE_ID, $sort, $select_sort);
        //echo "<pre>";print_r($result);exit;
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
        $this->multitasking_model->updateReviewCommentTaskAnswer($id);
        echo json_encode(array('status' => 'success'));
    }

    public function helpTask($task_id = null){
        if (isset($_POST) && sizeof($_POST) > 0) {
            if (!is_numeric($task_id)) {
                show_404();
            } else {
                $checkPermEdit = $this->multitasking_model->checkPermHelp($task_id, USER_COOKIE_ID);
                if (empty($checkPermEdit)) {
                    echo json_encode(array('status' => 'no_permission'));
                } else {
                    $post['new_respons'] = $_POST['new_respons'];
                    $who_ins = $_POST['who_insert'];
                    $result = $this->multitasking_model->insertHelpRespons($post, $task_id, $who_ins);
                    if ($result == 'true') {
                        echo json_encode(array('status' => 'success'));
                    } else {
                        echo json_encode(array('status' => 'error'));
                    }
                }
            }
        } else {
            if (!is_numeric($task_id)) {
                show_404();
            } else {
                $checkPermEdit = $this->multitasking_model->checkPermHelp($task_id, USER_COOKIE_ID);
                if (empty($checkPermEdit)) {
                    show_404();
                } else {
                    $data['help_multitask'] = $this->multitasking_model->getTask($task_id);
                    if (isset($data['help_multitask'][0]->multitask_id)) {
                        $data['employee'] = $this->multitasking_model->getUsers();
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
        $employee = $this->multitasking_model->getUsers();
        if (!empty($employee)) {
            echo json_encode(array('status' => 'success', 'result' => $employee));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function analytLink(){
        WorkInMultitask::clearCookies();
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

    public function addJalob(){
        $post['user_id'] = USER_COOKIE_ID;
        $post['text'] = htmlspecialchars(strip_tags($_POST['text']));
        $post['date'] = date('Y-m-d H:i:s');
        $result = $this->multitasking_model->addJalob($post);
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
}