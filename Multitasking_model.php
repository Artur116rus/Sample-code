<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

class Multitasking_model extends MY_Model{

    static $modelTable = 'multitask';

    public function __construct(){
        parent::__construct();
    }

    public function getPersonal($post){
        $query =  $this->db->query("SELECT surname,name,middlename FROM employee WHERE surname LIKE '%".$post['responsible']."%' OR name LIKE '%".$post['responsible']."%' OR middlename LIKE '%".$post['responsible']."%'");
        $result = $query->result();
        return $result;
    }

    /**
     * @param $post
     * @param $array
     * @param null $user_id - используется в случае, если метод вызывается из андроид-приложения,
     *                          где отсутствуют куки, и константа USER_COOKIE_ID будет не определена.
     * @return mixed
     */
    public function addMultitask($post, $array, $user_id = null){

        if (isset($user_id)) {
            $tuser_id = $user_id;
        } else {
            if( isset($post['iniciator']) ){
                 $tuser_id = $post['iniciator'];
            } else {
                 $tuser_id = USER_COOKIE_ID;
            }
        }

        $this->db->insert('multitask', $post);
        $array['task_id'] = $this->db->insert_id();

        $count = count($array['responsible']);
        //print_r($count);exit;
        for($i = 0; $i < $count; $i++){
            $this->db->query("INSERT INTO responsible_task (task_id, user_id, who_insert_id)
                            VALUES ('".$array['task_id']."', '".$array['responsible'][$i]."', '" . $tuser_id . "');");
        }
        for($i = 0; $i < $count; $i++){
            $this->db->query("INSERT INTO reminders_tasks (user_id, task_id, date)
                            VALUES ('".$array['responsible'][$i]."', '".$array['task_id']."', now());");
        }

        $checkDepartmentUsers = $this->db->query("SELECT employee.dep_id
                                                    FROM users LEFT JOIN employee
                                                    ON users.employee_id = employee.id
                                                    WHERE users.id = " . $tuser_id . "
        ");
        $result_department = $checkDepartmentUsers->result();
        if(isset($result_department[0]) && !empty($result_department)){
            if($result_department[0]->dep_id == DEPARTMENT_ID_3){
                $notice_new['task_id'] = $array['task_id'];
                $query_two = $this->db->query("SELECT users.id FROM `users` LEFT JOIN employee ON users.employee_id = employee.id WHERE employee.dep_id = '".DEPARTMENT_ID_3."' AND employee.status = 1");
                $result_two = $query_two->result();
                foreach($result_two as $item){
                    $notice_new['user_id'] = $item->id;
                    $this->db->insert('noti_tasks_oykd', $notice_new);
                }
            }
        }
        $nt['ksat_id'] = md5($array['task_id']);
        $nt['ksat_t'] = md5($post['date_period']."_".$array['task_id']."_strip_tags");
        //echo "<pre>";print_r($nt);exit;
        $this->db->insert('update_tools', $nt);
        return $array['task_id'];
    }

    public function getTasksActive($user_id){
        $query_tasks = ("SELECT multitask.status,responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.* ,
                                                DATEDIFF(multitask.date_period, now()) as period, responsible_task.change_content,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                                responsible_task.recall as recall, responsible_task.date_recall as date_recall,
                                                employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, 
                                                LEFT(employee.middlename,1) as employee_middle_name,
                                                address.name as adr_name, address.address as adr_location
                                          FROM  multitask LEFT JOIN responsible_task
                                         ON responsible_task.task_id = multitask.id LEFT JOIN users
                                         ON responsible_task.who_insert_id = users.id LEFT JOIN employee
                                         ON users.employee_id = employee.id LEFT JOIN points
                                        ON multitask.points_id = points.id LEFT JOIN brand
                                        ON points.brand_id = brand.id LEFT JOIN address
                                        ON points.name_address_id = address.id
                                         WHERE responsible_task.user_id = '".$user_id."' AND multitask.status = 1 AND multitask.trash = 0 AND responsible_task.exit_t = 0
        ");
        if(isset($_COOKIE['sort']) && $_COOKIE['select_sort'] == "period"){
            $query_tasks .= (" ORDER BY period ".$_COOKIE['sort']."");
        } elseif(isset($_COOKIE['sort']) && $_COOKIE['select_sort'] == "task_id"){
            $query_tasks .= (" ORDER BY multitask.id ".$_COOKIE['sort']."");
        } elseif(isset($_COOKIE['sort']) && $_COOKIE['select_sort'] == "full"){
            $query_tasks .= (" ORDER BY multitask.full ".$_COOKIE['sort']."");
        } elseif(isset($_COOKIE['sort']) && $_COOKIE['select_sort'] == "date_period"){
            $query_tasks .= (" ORDER BY multitask.date_period ".$_COOKIE['sort']."");
        } elseif(isset($_COOKIE['sort']) && $_COOKIE['select_sort'] == "priority"){
            $query_tasks .= (" ORDER BY multitask.priority ".$_COOKIE['sort']."");
        } else {
            $query_tasks .= (" ORDER BY responsible_task.date_recall DESC, responsible_task.change_content DESC, responsible_task.declaim ASC, multitask.priority DESC, period ASC, date_perform_users ASC");
        }

        $q = $this->db->query($query_tasks);
        $tasks = $q->result();
        //echo "<pre>";print_r($tasks);exit;
        return $tasks;
    }

    public function getTasksActiveCount($user_id){
        $query_tasks = $this->db->query("SELECT multitask.id
                                          FROM  multitask LEFT JOIN responsible_task
                                         ON responsible_task.task_id = multitask.id LEFT JOIN users
                                         ON responsible_task.who_insert_id = users.id LEFT JOIN employee
                                         ON users.employee_id = employee.id
                                         WHERE responsible_task.user_id = '".$user_id."' AND multitask.status = 1 AND multitask.trash = 0
        ");

        $result = $query_tasks->num_rows();
        return $result;
    }

    public function getTasksVxForAndroid($user_id, $offset){
        $query_tasks = $this->db->query("SELECT multitask.status,
                                                responsible_task.*, 
                                                LEFT(multitask.full,130) as multitask_full, 
                                                multitask.*,
                                                DATEDIFF(multitask.date_period, now()) as period, 
                                                responsible_task.change_content,
                                                DATEDIFF(multitask.date_period, 
                                                COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                                responsible_task.recall as recall, 
                                                responsible_task.date_recall as date_recall,
                                                employee.surname as employee_surname, 
                                                LEFT(employee.name,1) as employee_name, 
                                                LEFT(employee.middlename,1) as employee_middle_name,
                                                address.name as adr_name, 
                                                address.address as adr_location
                                          FROM multitask 
                                          LEFT JOIN responsible_task
                                            ON responsible_task.task_id = multitask.id 
                                          LEFT JOIN users
                                            ON responsible_task.who_insert_id = users.id 
                                          LEFT JOIN employee
                                            ON users.employee_id = employee.id 
                                          LEFT JOIN points
                                            ON multitask.points_id = points.id 
                                          LEFT JOIN brand
                                            ON points.brand_id = brand.id 
                                          LEFT JOIN address
                                            ON points.name_address_id = address.id
                                         WHERE responsible_task.user_id = '".$user_id."' 
                                                AND multitask.status = 1 
                                                AND multitask.trash = 0
                                         ORDER BY multitask.id LIMIT ".$offset.", 25
        ");
        $result = $query_tasks->result();
        //echo "<pre>";print_r($tasks);exit;
        return $result;
    }


    public function getDonetask($user_id){
        $query_tasks = $this->db->query("SELECT multitask.status,responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.* ,
                                                DATEDIFF(multitask.date_period, now()) as period, responsible_task.change_content,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users
                                    FROM users LEFT JOIN responsible_task
                                        ON users.id = responsible_task.user_id LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = '".$user_id."' AND multitask.status = 2 AND multitask.trash = 0
                                    ORDER BY multitask.priority DESC, period ASC, date_perform_users ASC
         ");
        $result = $query_tasks->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getDonetaskCount($user_id){
        $query_tasks = $this->db->query("SELECT multitask.id
                                    FROM users LEFT JOIN responsible_task
                                        ON users.id = responsible_task.user_id LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = '".$user_id."' AND multitask.status = 2 AND multitask.trash = 0
         ");
        $result = $query_tasks->num_rows();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTasksClose($user_id){
        $query_tasks = $this->db->query("SELECT multitask.status,responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.* ,
                                                DATEDIFF(multitask.date_period, now()) as period, responsible_task.change_content,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users
                                    FROM users LEFT JOIN responsible_task
                                        ON users.id = responsible_task.user_id LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = '".$user_id."' AND multitask.status = 3 AND multitask.trash = 0
                                    ORDER BY multitask.priority DESC, period ASC, date_perform_users ASC
         ");
        $tasks = $query_tasks->result();
        return $tasks;
    }

    public function getTasksCloseCount($user_id){
        $query_tasks = $this->db->query("SELECT multitask.id
                                    FROM users LEFT JOIN responsible_task
                                        ON users.id = responsible_task.user_id LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = '".$user_id."' AND multitask.status = 3 AND multitask.trash = 0
         ");
        $tasks = $query_tasks->num_rows();
        return $tasks;
    }

    public function getAwaitingModer($user_id){
        $query =  $this->db->query("SELECT responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.*, DATEDIFF(now(),multitask.date_period) as period
                                    FROM users LEFT JOIN responsible_task
                                      ON users.id = responsible_task.user_id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = ".$user_id." AND multitask.trash != 1 AND multitask.status != 3
                                    ORDER BY  multitask.priority DESC, period DESC
        ");
        $result = $query->result();
        return $result;
    }


    public function getAwaitingModerAll(){
        $query =  $this->db->query("SELECT multitask.*, LEFT(multitask.full,130) as multitask_full, responsible_task.who_insert_id,  users.id as employee_user_id,
                                                employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                                DATEDIFF(now(),multitask.date_period) as period
                                        FROM users LEFT JOIN responsible_task
                                            ON users.id = responsible_task.user_id LEFT JOIN multitask
                                            ON responsible_task.task_id = multitask.id LEFT JOIN employee
                                            ON users.employee_id = employee.id
                                    WHERE  multitask.moder = 3
                                    GROUP BY multitask.full
                                    ORDER BY  multitask.priority DESC, period DESC"
        );
        $result = $query->result();
        return $result;
    }

    public function getNeDobroModer($user_id){
        $query =  $this->db->query("SELECT responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.*, DATEDIFF(now(),multitask.date_period) as period
                                    FROM users LEFT JOIN employee
                                        ON users.employee_id = employee.id LEFT JOIN responsible_task
                                      ON users.id = responsible_task.user_id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE users.id = ".$user_id." AND multitask.trash != 1
                                    ORDER BY  multitask.priority DESC, period DESC"
        );
        $result = $query->result();
        return $result;
    }

    public function getTask($task_id){
        $query = $this->db->query("SELECT 
                                    employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name,
                                    employee.dep_id as department, 
                                    employee.phone as phone,
                                    multitask.id as multitask_id, 
                                    multitask.full as multitask_full, 
                                    multitask.date as multitask_date, 
                                    multitask.date as  multitask_date_my,
                                    multitask.date as  multitask_date_create_full,
                                    multitask.date_begin as  multitask_date_begin_full,
                                    multitask.date_period as  multitask_date_period_full,
                                    multitask.date_period as multitask_date_period, 
                                    multitask.priority as multitask_priority, 
                                    multitask.status as multitask_status, 
                                    multitask.image as mutitask_file,
                                    users.id as user_id, 
                                    IF(multitask.points_id != 0, multitask.points_id, 0) as points_id, 
                                    multitask.kurator as kurator,
                                    points.city as points_city, 
                                    points.street as points_street, 
                                    points.building as points_building,
                                    brand.text as brand_name, 
                                    address.name as address_name, 
                                    responsible_task.who_insert_id, 
                                    responsible_task.user_id as responsible_task_user_id,
                                    DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                    multitask.date_begin as multitask_date_begin, 
                                    multitask.date_perform as date_perform, 
                                    multitask.date_close as date_close,
                                    avtopark.name as avto_name, 
                                    avtopark.number as avto_number
                                    FROM responsible_task 
                                      LEFT JOIN users
                                        ON responsible_task.user_id = users.id 
                                      LEFT JOIN employee
                                        ON users.employee_id = employee.id 
                                      LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id 
                                      LEFT JOIN points
                                        ON multitask.points_id = points.id 
                                      LEFT JOIN brand
                                        ON points.brand_id = brand.id 
                                      LEFT JOIN address
                                        ON points.name_address_id = address.id 
                                      LEFT JOIN avtopark
                                        ON multitask.avtopark_id = avtopark.id
                                    WHERE responsible_task.task_id = ".$task_id." AND multitask.trash != 1
                                    GROUP BY responsible_task.task_id;
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTaskCurator($task_id){
        $query = $this->db->query("SELECT employee.surname as employee_curator_surname, 
                                          employee.name as employee_curator_name, 
                                          employee.middlename as employee_curator_middle_name,
                                          users.id as user_id
                                    FROM multitask 
                                    LEFT JOIN users
                                      ON multitask.kurator = users.id 
                                    LEFT JOIN employee
                                      ON users.employee_id = employee.id
                                    WHERE multitask.id = ".$task_id." AND multitask.trash != 1
            ");
        $result = $query->result();
        return $result;
    }

    public function getTaskFOrTypeTask($task_id){
        $query = $this->db->query("SELECT employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name,
                                      employee.dep_id as department, employee.phone as phone,
                                      curators.surname as curator_surname, curators.name as curator_name, curators.middle_name as curator_middle_name,
                                      multitask.id as multitask_id, multitask.full as multitask_full, multitask.date as multitask_date,
                                      multitask.date_period as multitask_date_period, multitask.priority as multitask_priority, 
                                      multitask.status as multitask_status, multitask.image as mutitask_file,
                                      users.id as user_id, multitask.points_id,
                                      points.city as points_city, points.street as points_street, points.building as points_building,
                                      brand.text as brand_name,address.name as address_name, responsible_task.who_insert_id, responsible_task.user_id as responsible_task_user_id
                                    FROM responsible_task LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN curators
                                      ON employee.curator_id = curators.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id LEFT JOIN points
                                      ON multitask.points_id = points.id LEFT JOIN brand
                                      ON points.brand_id = brand.id LEFT JOIN address
                                      ON points.name_address_id = address.id
                                    WHERE responsible_task.task_id = ".$task_id." AND multitask.trash != 1
                                    GROUP BY multitask.id
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getForTasksEmployee($task_id){
        $query = $this->db->query("SELECT employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name, employee.phone,
                                          users.id as user_id, responsible_task.exit_t, responsible_task.voice_t
                                    FROM responsible_task LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.task_id = ".$task_id."  AND multitask.trash != 1
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getForTasksEmployeeCount($task_id){
        $query = $this->db->query("SELECT users.id 
                                    FROM responsible_task 
                                      LEFT JOIN users ON responsible_task.user_id = users.id 
                                      LEFT JOIN employee ON users.employee_id = employee.id 
                                      LEFT JOIN multitask ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.task_id = ".$task_id."  
                                        AND multitask.trash != 1 
                                        AND responsible_task.exit_t = 0
        ");
        $result = $query->num_rows();
        return $result;
    }

    // Костыль
    public function getForTasksEmployeeNoti($task_id, $user_id){
        $query = $this->db->query("SELECT employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name, employee.phone,
                                          users.id as user_id, users.telegram_id
                                    FROM responsible_task LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.task_id = ".$task_id."  AND multitask.trash != 1 AND users.id != ".$user_id."
        ");
        $result = $query->result_array();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }



    public function getWhoInsertTask($task_id){
        $query = $this->db->query("SELECT users.id as user_id, employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name
                                    FROM responsible_task 
                                    LEFT JOIN users
                                      ON responsible_task.who_insert_id = users.id 
                                    LEFT JOIN employee
                                      ON users.employee_id = employee.id 
                                    LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.task_id = ".$task_id." AND multitask.trash != 1
                                    GROUP BY responsible_task.task_id
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getWhoInsertTaskNoti($task_id, $user_id){
        $query = $this->db->query("SELECT users.id as user_id, employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name,
                                          users.telegram_id
                                    FROM responsible_task LEFT JOIN users
                                      ON responsible_task.who_insert_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.task_id = ".$task_id." AND multitask.trash != 1 AND users.id != ".$user_id."
                                    GROUP BY responsible_task.task_id
        ");
        $result = $query->result_array();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getEditEmployee($employee_id){
        $query = $this->db->get_where('employee', array('id' => $employee_id));
        $result = $query->result();
        return $result;
    }

    public function editTask($post, $task_id, $array, $log, $new_respons){
        //echo "<pre>";print_r($task_id);exit;
        if(isset($log) &&  !empty($log)){
            $data['user_id'] = USER_COOKIE_ID;
            $data['task_id'] = $task_id;
            $data['date'] = date('Y-m-d H:i:s');
            $data['log'] = $log;
            $this->db->insert('task_logs', $data);
        }
        if(empty($array)){
            $this->db->update('multitask', $post, array('id' => $task_id));
        } else {
            $this->db->update('multitask', $post, array('id' => $task_id));
            $query = $this->db->query("SELECT *
                                    FROM responsible_task
                                    WHERE task_id = ".$task_id."
             ");
            $result = $query->result();
            foreach($result as $item){
                $this->db->delete('responsible_task', array('user_id' => $item->user_id, 'task_id' => $item->task_id));
                $this->db->delete('reminders_tasks', array('user_id' => $item->user_id, 'task_id' => $item->task_id));
            }
            $this->db->delete('responsible_task', array('task_id' => $task_id));
            //$this->db->query("DELETE FROM responsible_task WHERE responsible_task.task_id = ".$task_id."");
            $count = count($array);
            //print_r($count);exit;
            for($i = 0; $i < $count; $i++){
                $this->db->query("INSERT INTO responsible_task (task_id, user_id, who_insert_id)
                            VALUES ('".$task_id."', '".$array[$i]."', '".USER_COOKIE_ID."');");
                $this->db->query("INSERT INTO reminders_tasks (task_id, user_id, date)
                            VALUES ('".$task_id."', '".$array[$i]."', 'now()');");
            }
        }
        if(!empty($new_respons)) {
            $countResp = count($new_respons);
            for ($i = 0; $i < $countResp; $i++) {
                $this->db->query("UPDATE responsible_task
                             SET who_insert_id = ".$new_respons['who_insert_id']."
                             WHERE task_id = ".$task_id."
                 ");
            }
//            if ($new_respons['who_insert_id'] != USER_COOKIE_ID) {
//                // обновим куратора - установим куратором текущего пользователя, который сменил инициатора с себя на $new_respons['who_insert_id']
//                $this->db->query("UPDATE multitask mt SET mt.kurator = " . USER_COOKIE_ID . " WHERE mt.id = " . $task_id);
//            }
        }
        return true;
    }

    public function addComment($post, $data, $type_task, $respons, $iniciator){
        //echo "<pre>";print_r($post);exit;
        /*$this->db->select('user_id, who_insert_id, task_id');
        $this->db->from('responsible_task');
        $this->db->where('task_id', $post['task_id']);
        $query = $this->db->get();
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        foreach ($result as $em) {
            if($em->user_id != USER_COOKIE_ID){
                $new_update_task['change_content'] = 1;
                foreach ($result as $selUs) {
                    $this->db->update('responsible_task', $new_update_task, array('task_id' => $selUs->task_id, 'user_id' => $selUs->user_id));
                }
            }
            if($em->who_insert_id != USER_COOKIE_ID){
                $new_update_task['change_content_out'] = 1;
                if(isset($result[0]) && !empty($result[0])){
                    $this->db->update('responsible_task', $new_update_task, array('task_id' => $result[0]->task_id, 'who_insert_id' => $result[0]->who_insert_id));
                }
            }
        }*/
        $this->db->insert('comments_task', $post);
        $last_id = $this->db->insert_id();
        $query = $this->db->query("SELECT com.id,com.task_id,com.user_id,com.text, com.date, em.name,em.surname,em.middlename,us.login,com.files,
                                          multitask.full, multitask.date as mult_date, multitask.date_begin, multitask.date_period, multitask.status,
                                          points.street, points.building
                                        FROM comments_task as com LEFT JOIN users as us
                                          ON  com.user_id = us.id LEFT JOIN employee as em
                                          ON us.employee_id = em.id LEFT JOIN multitask
                                          ON com.task_id = multitask.id LEFT JOIN points
                                          ON points.id = multitask.points_id
                                        WHERE com.id = '".$last_id."'"
        );
        $result = $query->result();
        //echo "<pre>";print_r($result[0]->user_id.' '.$iniciator[0]->user_id);exit;
        if($result[0]->user_id != $iniciator[0]->user_id){
            $new_noti_user['comment_id'] = &$last_id;
            $new_noti_user['user_id'] = $iniciator[0]->user_id;
            $new_noti_user['type_task'] = 1;
            $this->db->insert('noti_comment_user', $new_noti_user);
        }
        foreach($respons as $resp){
            if($result[0]->user_id != $resp->user_id){
                $new_noti_user['comment_id'] = &$last_id;
                $new_noti_user['user_id'] = $resp->user_id;
                $new_noti_user['type_task'] = 0;
                $this->db->insert('noti_comment_user', $new_noti_user);
            }
        }


        $checkDepartmentUsers = $this->db->query("SELECT employee.dep_id
                                                    FROM users LEFT JOIN employee
                                                    ON users.employee_id = employee.id
                                                    WHERE users.id = ".$post['user_id']."
        ");
        $result_department = $checkDepartmentUsers->result();
        if(isset($result_department[0]) && !empty($result_department)){
            if($result_department[0]->dep_id != DEPARTMENT_ID_3){
                $notice_new['comment_id'] = $last_id;
                $query_two = $this->db->query("SELECT users.id FROM `users` LEFT JOIN employee ON users.employee_id = employee.id WHERE employee.dep_id = '".DEPARTMENT_ID_3."' AND employee.status = 1");
                $result_two = $query_two->result();
                foreach($result_two as $item){
                    $notice_new['user_id'] = $item->id;
                    $this->db->insert('notice_tasks_comment', $notice_new);

                }
            }
        }

        return $result;
    }

    public function addCommentAnswer($post, $task_id){
        $this->db->insert('comment_answer', $post);
        $last_id = $this->db->insert_id();
        $getComment = $this->db->query("SELECT comments_task.task_id,comment_answer.text, multitask.status as mult_status,
                                            multitask.full, multitask.date as date_write, multitask.date_begin, multitask.date_period, multitask.id as mult_id,
                                            points.street, points.building
                                        FROM comments_task  INNER JOIN comment_answer
                                        ON comment_answer.comment_id = comments_task.id INNER JOIN multitask
                                        ON comments_task.task_id = multitask.id LEFT JOIN points
                                        ON multitask.points_id = points.id
                                        WHERE comment_answer.id = ".$last_id."
        ");
        $result = $getComment->result_array();
        $employeeTask = $this->db->query("SELECT user_id
                                          FROM responsible_task
                                          WHERE task_id = ".$task_id."
        ");
        $result_one = $employeeTask->result();
        //echo "<pre>";print_r($result_one);exit;
        $noti['task_id'] = $task_id;
        $noti['comment_answer_id'] = $last_id;
        foreach($result_one as $em){
            if($em->user_id != USER_COOKIE_ID){
                $noti['user_id'] = $em->user_id;
                //echo "<pre>";print_r($noti);
                $this->db->insert('noti_comment_answer', $noti);
            }
        }
        //exit;
        $employeeTaskWho = $this->db->query("SELECT who_insert_id
                                          FROM responsible_task
                                          WHERE task_id = ".$task_id."
                                          GROUP BY task_id
        ");
        $result_who = $employeeTaskWho->result();
        //echo "<pre>";print_r($result_who);exit;
        $noti_who['task_id'] = $task_id;
        $noti_who['comment_answer_id'] = $last_id;
        foreach($result_who as $em){
            if($em->who_insert_id != USER_COOKIE_ID){
                $noti_who['user_id'] = $em->who_insert_id;
                $this->db->insert('noti_comment_answer', $noti_who);
            }
        }
        return $result;
    }

    public function getCommentAnswer($user_id){
        $query = $this->db->query("SELECT comments_task.task_id, comments_task.user_id as who_user_id, LEFT(comment_answer.text,70) as text,  DATE_FORMAT(comments_task.date,'%d.%m.%y') as date_n,
                                          noti_comment_answer.id as id 
                                    FROM noti_comment_answer INNER JOIN comment_answer
                                     ON noti_comment_answer.comment_answer_id = comment_answer.id INNER JOIN comments_task 
                                    ON comments_task.id = comment_answer.comment_id
                                    WHERE noti_comment_answer.user_id = ".$user_id."
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function countGetCommentAnswer($user_id){
        $query = $this->db->query("SELECT comments_task.task_id, comments_task.user_id as who_user_id, comments_task.text,  DATE_FORMAT(comments_task.date,'%d.%m.%y') as date_n,
                                          comment_answer.*
                                    FROM noti_comment_answer INNER JOIN comment_answer
                                     ON noti_comment_answer.comment_answer_id = comment_answer.id INNER JOIN comments_task 
                                    ON comments_task.id = comment_answer.comment_id
                                    WHERE noti_comment_answer.user_id = ".$user_id."
        ");
        $result = $query->num_rows();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }


    // Добавляет комментарии для пользователей, которые добавили отдел ОУКД.
    public function addCommentAllUsers($post){
        $this->db->insert('comments_task', $post);
        $last_id = $this->db->insert_id();
        $query = $this->db->query("SELECT com.id,com.task_id,com.user_id,com.text, com.date, em.name,em.surname,em.middlename,us.login,com.files
                                        FROM comments_task as com LEFT JOIN users as us
                                          ON  com.user_id = us.id LEFT JOIN employee as em
                                          ON us.employee_id = em.id
                                        WHERE com.id = '".$last_id."'"
        );
        $result = $query->result();
        return $result;
    }

    public function getTasks($task_id, $sort){
        $query = $this->db->query("SELECT comment_answer.text as answer_text, comment_answer.id as comm_answer_id,
                                            em2.surname as em2_surname, em2.name as em2_name,em2.middlename as em2_middlename,
                                            comments_task.id as com_task_id, comments_task.user_id as  comments_task_user_id,
                                          em1.surname as em1_surname, em1.name as em1_name,em1.middlename as em1_middlename,
                                      comments_task.id as com_task_id, comments_task.task_id,comments_task.user_id,
                                      comments_task.text,comments_task.date, comments_task.files,
                                      u1.login
                                    FROM comments_task LEFT JOIN users u1
                                        ON comments_task.user_id = u1.id LEFT JOIN employee em1
                                        ON u1.employee_id = em1.id LEFT JOIN comment_answer
                                        ON comments_task.id = comment_answer.comment_id LEFT JOIN users u2
                                      ON comment_answer.user_id = u2.id LEFT JOIN employee em2
                                      ON em2.id = u2.employee_id
                                    WHERE task_id = ".$task_id."
                                    GROUP BY comments_task.id
                                    ORDER BY comments_task.date {$sort}"
        );
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTasksPermissions($user_id){
        $query = $this->db->get_where('permissions_users', array('user_id' => $user_id));
        $result = $query->result();
        return $result;
    }

    public function getTasksAll(){
        /*$query = $this->db->query("SELECT multitask.id, employee.surname, employee.name, employee.middle_name, users.id,users.login,multitask.name, multitask.short,multitask.full,multitask.date,multitask.date_period,multitask.status,multitask.moder,multitask.priority
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id"
        );*/
        $query = $this->db->query("SELECT * FROM multitask");
        $result = $query->result();
        return $result;
    }

    public function getTasksChiefDepartment($user_id){
        $query = $this->db->query("SELECT * FROM users");
        $result = $query->result();
        return true;
    }

    public function getUsers(){
        $query =  $this->db->query("
            SELECT users.id, surname,employee.name,middlename, position_employee.name as pos_name
            FROM B users INNER JOIN employee
              ON users.employee_id = employee.id LEFT JOIN position_employee
              ON employee.job_id = position_employee.job_id
            ORDER BY employee.surname ASC;"
        );
        $result = $query->result();
        return $result;
    }

    public function getUsersForSogl(){
        $query =  $this->db->query("
            SELECT users.id, surname,employee.name,middlename, position_employee.name as pos_name
            FROM users INNER JOIN employee
              ON users.employee_id = employee.id LEFT JOIN position_employee
              ON employee.job_id = position_employee.job_id
              WHERE active = 1
            ORDER BY employee.surname ASC;"
        );
        $result = $query->result();
        return $result;
    }

    // Для edit. В качестве результата выводим масив
    public function getUsersArray(){
        $query =  $this->db->query("SELECT users.id, employee.surname,employee.name,employee.middlename, position_employee.name as pos_name
                                    FROM users LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN position_employee
                                      ON employee.job_id = position_employee.job_id
                                      WHERE users.active = 1
                                    ORDER BY employee.surname ASC;"
        );
        $result = $query->result_array();
        return $result;
    }

    public function getDepartmentUser($user_id){
        $query =  $this->db->query("SELECT us.*,em.dep_id
                                    FROM users as us LEFT JOIN employee as em
                                      ON us.employee_id = em.id
                                    WHERE us.id = {$user_id}"
        );
        $result = $query->result();
        return $result;
    }

    public function getPeriodDate($post){
        $query =  $this->db->query("SELECT * FROM multitask WHERE multitask.date >= '".$post['ot']."' AND multitask.date <= '".$post['to']."'");
        $result = $query->result();
        return $result;
    }

    public function getChiefUser($user_id){
        $query =  $this->db->query("SELECT chief.department_id, users.login FROM users LEFT JOIN chief ON users.id = chief.user_id WHERE users.id = '".$user_id."'");
        $result = $query->result();
        return $result;
    }

    public function deleteTask($task_id){
        $this->db->delete('multitask', array('id' => $task_id));
        $this->db->delete('responsible_task', array('task_id' => $task_id));
        return true;
    }

    public function actionDeclaim($post, $task_id){
        //print_r($post);exit;
        /*$this->db->query("UPDATE responsible_task
                            SET date_declaim='".$post['date_declaim']."', declaim=".$post['declaim']."
                            WHERE task_id = ".$task_id." AND user_id = ".USER_COOKIE_ID."
                            ");*/
        $this->db->update('responsible_task', $post, array('task_id' => $task_id, 'user_id' => USER_COOKIE_ID));
        return true;
    }


    public function getDeclaim($task_id){
        $query =  $this->db->query("SELECT declaim FROM responsible_task WHERE task_id = '".$task_id."'");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result[0]->declaim;
    }

    public function getWhoInsertActive($user_id){
        $query = $this->db->query("SELECT multitask.status,responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.* ,
                                                DATEDIFF(multitask.date_period, now()) as period, responsible_task.change_content, responsible_task.change_content_out,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                                 employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, 
                                                 LEFT(employee.middlename,1) as employee_middle_name,
                                                 address.name as adr_name, address.address as adr_location
                         FROM responsible_task LEFT JOIN multitask
                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                          ON responsible_task.user_id = users.id LEFT JOIN employee
                          ON users.employee_id = employee.id LEFT JOIN points
                        ON multitask.points_id = points.id LEFT JOIN brand
                        ON points.brand_id = brand.id LEFT JOIN address
                         ON points.name_address_id = address.id
                        WHERE responsible_task.who_insert_id = '".$user_id."' AND multitask.status = 1 AND multitask.trash = 0
                        GROUP BY multitask.id
                        ORDER BY responsible_task.change_content_out DESC, multitask.priority DESC, period ASC, date_perform_users ASC
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getWhoInsertActiveCount($user_id){
        $query = $this->db->query("SELECT multitask.id
                         FROM responsible_task 
                          LEFT JOIN multitask
                            ON responsible_task.task_id = multitask.id                           
                        WHERE responsible_task.who_insert_id = '".$user_id."' AND multitask.status = 1 AND multitask.trash = 0
                        GROUP BY multitask.id
        ");
        $result = $query->num_rows();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTasksIsxForAndroid($user_id, $offset){
        $query = $this->db->query("SELECT multitask.status,responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.* ,
                                                DATEDIFF(multitask.date_period, now()) as period, responsible_task.change_content, responsible_task.change_content_out,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                                 employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, 
                                                 LEFT(employee.middlename,1) as employee_middle_name,
                                                 address.name as adr_name, address.address as adr_location
                         FROM responsible_task LEFT JOIN multitask
                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                          ON responsible_task.user_id = users.id LEFT JOIN employee
                          ON users.employee_id = employee.id LEFT JOIN points
                        ON multitask.points_id = points.id LEFT JOIN brand
                        ON points.brand_id = brand.id LEFT JOIN address
                         ON points.name_address_id = address.id
                        WHERE responsible_task.who_insert_id = '".$user_id."' AND multitask.status = 1 AND multitask.trash = 0
                        GROUP BY multitask.id
                        ORDER BY responsible_task.change_content_out DESC, multitask.priority DESC, period ASC, date_perform_users ASC,
                        multitask.id LIMIT ".$offset.", 25
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getWhoInsertDone($user_id){
        $query = $this->db->query("SELECT multitask.status,responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.* ,
                                                DATEDIFF(multitask.date_period, now()) as period, responsible_task.change_content, responsible_task.change_content_out,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                                 employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name
                         FROM responsible_task LEFT JOIN multitask
                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                          ON responsible_task.user_id = users.id LEFT JOIN employee
                          ON users.employee_id = employee.id
                        WHERE responsible_task.who_insert_id = '".$user_id."' AND multitask.status = 2 AND multitask.trash = 0
                        GROUP BY multitask.id
                        ORDER BY responsible_task.change_content_out DESC, multitask.priority DESC, period ASC, date_perform_users ASC
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getInsertDonetaskCount($user_id){
        $query = $this->db->query("SELECT multitask.id
                         FROM responsible_task LEFT JOIN multitask
                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                          ON responsible_task.user_id = users.id LEFT JOIN employee
                          ON users.employee_id = employee.id
                        WHERE responsible_task.who_insert_id = '".$user_id."' AND multitask.status = 2 AND multitask.trash = 0
                        GROUP BY multitask.id
        ");
        $result = $query->num_rows();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getWhoInsertExpectation($user_id){
        $query = $this->db->query("SELECT responsible_task.*,users.login,multitask.*, LEFT(multitask.full,130) as multitask_full
                                    FROM responsible_task LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id LEFT JOIN users
                                        ON responsible_task.user_id = users.id
                                    WHERE responsible_task.who_insert_id = '".$user_id."' AND multitask.trash != 1 AND multitask.status != 3 AND multitask.status != 5
                                    ORDER BY multitask.priority DESC,multitask.date DESC"
        );
        $result = $query->result();
        return $result;
    }

    public function getWhoInsertClose($user_id){
        $query = $this->db->query("SELECT multitask.status,responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.* ,
                                                DATEDIFF(multitask.date_period, now()) as period, responsible_task.change_content,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users
                                    FROM responsible_task LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id LEFT JOIN users
                                        ON responsible_task.user_id = users.id
                                    WHERE responsible_task.who_insert_id = ".$user_id." AND multitask.status = 3 AND multitask.trash = 0
                                    GROUP BY multitask.id
                                     ORDER BY  multitask.priority DESC, period ASC, date_perform_users ASC
        ");
        $result = $query->result();
        return $result;
    }

    public function getWhoInsertCloseCount($user_id){
        $query = $this->db->query("SELECT multitask.id
                                    FROM responsible_task LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id LEFT JOIN users
                                        ON responsible_task.user_id = users.id
                                    WHERE responsible_task.who_insert_id = ".$user_id." AND multitask.status = 3 AND multitask.trash = 0
                                    GROUP BY multitask.id
        ");
        $result = $query->num_rows();
        return $result;
    }

    public function getWhoInsertReject($user_id){
        $query = $this->db->query("SELECT responsible_task.*,users.login,multitask.*, LEFT(multitask.full,130) as multitask_full
                                    FROM responsible_task LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id LEFT JOIN users
                                        ON responsible_task.user_id = users.id
                                    WHERE responsible_task.who_insert_id = ".$user_id."  AND multitask.status != 3 AND multitask.trash != 1
                                    ORDER BY multitask.priority DESC,multitask.date DESC"
        );
        $result = $query->result();
        return $result;
    }

    public function getTaskMyAll($user_id){
        $query = $this->db->query("SELECT responsible_task.*,users.login,users.id,multitask.*, LEFT(multitask.full,130) as multitask_full,
                                          DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id LEFT JOIN users
                                      ON responsible_task.user_id = users.id
                                      WHERE  (responsible_task.who_insert_id = ".$user_id." OR responsible_task.user_id = ".$user_id.") AND multitask.trash = 0
                                      GROUP BY multitask.id
                                    ORDER BY  multitask.priority DESC, date_perform_users ASC
        ");
        $result = $query->result();
        return $result;
    }

    public function getTaskMyAllCount($user_id){
        $query = $this->db->query("SELECT multitask.id
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id LEFT JOIN users
                                      ON responsible_task.user_id = users.id
                                      WHERE  (responsible_task.who_insert_id = ".$user_id." OR responsible_task.user_id = ".$user_id.") AND multitask.trash = 0
                                      GROUP BY multitask.id
        ");
        $result = $query->num_rows();
        return $result;
    }

    public function addAssepted($post, $task_id, $comment){
        if(!empty($comment['text'])){
            $this->db->update('multitask', $post, array('id' => $task_id));
            $comment['task_id'] = $task_id;
            $comment['user_id'] = get_cookie('id', TRUE);
            $comment['date'] = date('Y-m-d H:i:s');
            $this->db->insert('comments_task', $comment);
        } else {
            $this->db->update('multitask', $post, array('id' => $task_id));
        }
        return true;
    }


    public function getTasksActiveAllUsers(){
        $query_tasks = $this->db->query("SELECT multitask.*, LEFT(multitask.full,130) as multitask_full,
                                                responsible_task.who_insert_id,
                                                users.id as employee_user_id,
                                                employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                                DATEDIFF(multitask.date_period, now()) as period
                                        FROM users LEFT JOIN responsible_task
                                            ON users.id = responsible_task.user_id LEFT JOIN multitask
                                            ON responsible_task.task_id = multitask.id LEFT JOIN employee
                                            ON users.employee_id = employee.id
                                        WHERE multitask.status != 3 AND multitask.status != 5 AND multitask.moder != 1 AND multitask.moder != 3
                                        GROUP BY multitask.id
                                        ORDER BY multitask.priority DESC, period DESC"
        );
        $tasks = $query_tasks->result();
        //echo "<pre>";print_r($tasks);exit;
        return $tasks;
    }

    public function getTasksCloseAllUsers(){
        $query = $this->db->query("SELECT multitask.*, LEFT(multitask.full,130) as multitask_full, responsible_task.who_insert_id,  users.id as employee_user_id,
                                                employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                                DATEDIFF(multitask.date_period, now()) as period
                                        FROM users LEFT JOIN responsible_task
                                            ON users.id = responsible_task.user_id LEFT JOIN multitask
                                            ON responsible_task.task_id = multitask.id LEFT JOIN employee
                                            ON users.employee_id = employee.id
                                    WHERE  multitask.status = 3 AND multitask.moder != 1 AND multitask.moder != 3
                                    ORDER BY  multitask.priority DESC, period DESC"
        );
        $tasks = $query->result();
        return $tasks;
    }

    public function getNeDobroModerAllUsers(){
        $query =  $this->db->query("SELECT multitask.*, LEFT(multitask.full,130) as multitask_full, responsible_task.who_insert_id,  users.id as employee_user_id,
                                                employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                                DATEDIFF(multitask.date_period, now()) as period
                                        FROM users LEFT JOIN responsible_task
                                            ON users.id = responsible_task.user_id LEFT JOIN multitask
                                            ON responsible_task.task_id = multitask.id LEFT JOIN employee
                                            ON users.employee_id = employee.id
                                    WHERE multitask.moder = 1
                                    ORDER BY multitask.date_period DESC,multitask.priority DESC"
        );
        $result = $query->result();
        return $result;
    }

    public function countTaskActive($user_id){
        $query =  $this->db->query("SELECT COUNT(DISTINCT multitask.name) as count_id
                                    FROM  multitask LEFT JOIN responsible_task
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = '".$user_id."' AND status != 3 AND multitask.moder != 1 AND multitask.moder != 3 AND multitask.trash != 1
        ");
        $result = $query->result();
        return $result;
    }

    public function countTaskAwating($user_id){
        $query =  $this->db->query("SELECT COUNT(responsible_task.task_id) as count_id
                                    FROM users LEFT JOIN responsible_task
                                        ON users.id = responsible_task.user_id LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = ".$user_id." AND multitask.moder = 3 AND multitask.trash != 1
         ");
        $result = $query->result();
        return $result;
    }

    public function countTaskClose($user_id){
        $query =  $this->db->query("SELECT COUNT(multitask.id) as count_id
                                    FROM users LEFT JOIN responsible_task
                                        ON users.id = responsible_task.user_id LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = '".$user_id."' AND multitask.status = 3 AND multitask.moder != 1 AND multitask.moder != 3 AND multitask.trash != 1"
        );
        $result = $query->result();
        return $result;
    }

    public function countNeDobro($user_id){
        $query =  $this->db->query("SELECT COUNT(DISTINCT multitask.name) as count_id
                                    FROM  multitask LEFT JOIN responsible_task
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = ".$user_id." AND multitask.moder = 1 AND multitask.trash != 1"
        );
        $result = $query->result();
        return $result;
    }

    /*count для входящих*/
    public function countMyAllTask($user_id){
        $query =  $this->db->query("SELECT COUNT(DISTINCT multitask.id) as count_id
                                    FROM  multitask LEFT JOIN responsible_task
                                        ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = '".$user_id."' AND multitask.trash != 1"
        );
        $result = $query->result();
        return $result;
    }

    public function countWhoTaskActive($user_id){
        $query = $this->db->query("SELECT COUNT(DISTINCT multitask.id) as count_id
                                    FROM  multitask LEFT JOIN responsible_task
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.who_insert_id = ".$user_id." AND multitask.moder != 1 AND multitask.moder != 3 AND multitask.status != 3 AND multitask.trash != 1"
        );
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function countWhoTaskAwating($user_id){
        $query = $this->db->query("SELECT COUNT(DISTINCT multitask.id) as count_id
                                    FROM  multitask LEFT JOIN responsible_task
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.who_insert_id = ".$user_id." AND multitask.moder = 3 AND multitask.trash != 1"
        );
        $result = $query->result();
        return $result;
    }

    public function countWhoTaskClose($user_id){
        $query = $this->db->query("SELECT COUNT(DISTINCT multitask.id) as count_id
                                    FROM  multitask LEFT JOIN responsible_task
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.who_insert_id = ".$user_id." AND multitask.status = 3 AND multitask.trash != 1
                                    ORDER BY multitask.priority DESC,multitask.date DESC"
        );
        $result = $query->result();
        return $result;
    }

    public function countWhoReject($user_id){
        $query = $this->db->query("SELECT COUNT(DISTINCT multitask.name) as count_id
                                    FROM  multitask LEFT JOIN responsible_task
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.who_insert_id = ".$user_id." AND multitask.moder != 0 AND multitask.moder != 2 AND multitask.moder != 3 AND multitask.status != 3 AND multitask.trash != 1
                                    ORDER BY multitask.priority DESC,multitask.date DESC"
        );
        $result = $query->result();
        return $result;
    }

    /*count для taba "Исходящие"*/
    public function countWhoMyAllTask($user_id){
        $query =  $this->db->query("SELECT COUNT(DISTINCT multitask.id) as count_id
                                    FROM  multitask LEFT JOIN responsible_task
                                        ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.who_insert_id= '".$user_id."' AND multitask.trash != 1
        ");
        $result = $query->result();
        return $result;
    }

    /*count для taba "все"*/
    public function countaskMyAll($user_id){
        $query = $this->db->query("SELECT COUNT(DISTINCT multitask.id) as count_id
                                    FROM  multitask LEFT JOIN responsible_task
                                        ON responsible_task.task_id = multitask.id
                                    WHERE (responsible_task.user_id = ".$user_id." OR responsible_task.who_insert_id = ".$user_id.") AND multitask.id IS NOT NULL AND multitask.trash != 1
                                    ORDER BY multitask.priority DESC, multitask.date_period DESC"
        );
        $result = $query->result();
        return $result;
    }

    public function getCountTaskMyAllActive(){
        $query_tasks = $this->db->query("SELECT COUNT(DISTINCT multitask.id) as count_id
                                        FROM users LEFT JOIN responsible_task
                                            ON users.id = responsible_task.user_id LEFT JOIN multitask
                                            ON responsible_task.task_id = multitask.id
                                        WHERE status != 3 AND status != 5 AND multitask.moder != 1 AND multitask.moder != 3 AND multitask.trash != 1"
        );
        $tasks = $query_tasks->result();
        return $tasks;
    }

    public function getCountTaskMyAllAwating($user_id){
        $query =  $this->db->query("SELECT COUNT(DISTINCT multitask.full) as count_id, multitask.full
                                    FROM users LEFT JOIN responsible_task
                                      ON users.id = responsible_task.user_id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE  multitask.moder = 3"
        );
        $result = $query->result();
        return $result;
    }

    public function getCountTaskMyAllClose($user_id){
        $query = $this->db->query("SELECT COUNT(multitask.name) as count_id
                                    FROM users LEFT JOIN responsible_task
                                        ON users.id = responsible_task.user_id LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                    WHERE  multitask.status = 3 AND multitask.moder != 1 AND multitask.moder != 3"
        );
        $tasks = $query->result();
        return $tasks;
    }

    public function getCountTaskMyAllNeDobro($user_id){
        $query =  $this->db->query("SELECT COUNT(multitask.name) as count_id
                                    FROM users LEFT JOIN employee
                                        ON users.employee_id = employee.id LEFT JOIN responsible_task
                                      ON users.id = responsible_task.user_id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE multitask.moder = 1"
        );
        $result = $query->result();
        return $result;
    }



    /*
     * Печать заданий "Все задания"
     *
     * */

    public function getAllTaskEVPriority($post){
        $query = $this->db->query("SELECT DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period,users.id as                                                        user_id, multitask.id as task_id,points.street as points_street, points.building as points_building,points.city as points_city,
                                                multitask.full as full,  DATEDIFF(multitask.date_period, now()) as period, multitask.id as multitask_id,
                                                 DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                        WHERE responsible_task.user_id = " . $post['user_id'] . " AND multitask.priority = 4 AND multitask.trash = 0 AND multitask.status = ".$post['select_status']." 
                                        ORDER BY period ASC"
        );
        $result = $query->result();
        return $result;
    }

    public function getAllTaskEVPriorityCount($post){
        $query = $this->db->query("SELECT COUNT(multitask.id) as count_tasks
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                       WHERE responsible_task.user_id = " . $post['user_id'] . " AND multitask.priority = 4 AND multitask.trash = 0 AND multitask.status = ".$post['select_status']."
        ");
        $result = $query->result();
        return $result;
    }

    public function getAllTaskHighForUsersMyTasks($post){
        $query = $this->db->query("SELECT DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period,                                                        user_id, multitask.id as task_id,points.street as points_street, points.building as points_building,points.city as points_city,
                                                multitask.full as multitask_full,  DATEDIFF(multitask.date_period, now()) as period, multitask.id as multitask_id,
                                                 DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                        WHERE responsible_task.user_id = " . $post['user_id'] . " AND multitask.priority = 3 AND multitask.trash = 0 AND multitask.status = ".$post['select_status']."
                                        "
        );
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getAllTaskHighForUsersCount($post){
        $query =  $this->db->query("SELECT COUNT(multitask.id) as count_tasks
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                     WHERE responsible_task.user_id = " . $post['user_id'] . " AND multitask.priority = 3 AND multitask.trash = 0 AND multitask.status = ".$post['select_status']."
         ");
        $result = $query->result();
        return $result;
    }

    public function getAllTaskNoAveragetForUsers($post){
        //echo "<pre>";print_r($post);exit;
        $query = $this->db->query("SELECT DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period,users.id as user_id, multitask.id as task_id,points.street as points_street, points.building as points_building,points.city as points_city,
                                                multitask.full as full, DATEDIFF(multitask.date_period, now()) as period,
                                                  DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                        WHERE responsible_task.user_id = " . $post['user_id'] . " AND priority != 3 AND priority != 4 AND multitask.trash = 0 AND multitask.status = ".$post['select_status']."
                                        ORDER BY period ASC"
        );
        $result = $query->result();
        //array_push($result, $result[$i]);
        // }
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getAllTaskNoAverageCountForUsers($post){
        $query =  $this->db->query("SELECT COUNT(multitask.id) as count_tasks
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                     WHERE responsible_task.user_id = " . $post['user_id'] . " AND priority != 3 AND priority != 4 AND multitask.trash = 0 AND multitask.status = {$post['select_status']}"
        );
        $result = $query->result();
        return $result;
    }

    /*
     *
     * Конец печать "Все задания"
     * */


    public function getEmployeeFoPrint($post){
        //$count = count($post['user_id']);
        //echo $count;
        //echo "<pre>";print_r($post);exit;
        //for($i = 0; $i < $count; $i++){
        //print_r($post['user_id'][$i]);
        $query =  $this->db->query("SELECT users.id as user_id,employee.*, position_employee. name as name_job,
                                                  curators.id as curator_id, curators.surname as curator_surname, curators.name as curator_name, curators.middle_name as curator_middle_name
                                            FROM responsible_task LEFT JOIN multitask
                                                ON responsible_task.task_id = multitask.id LEFT JOIN users
                                                ON responsible_task.user_id = users.id LEFT JOIN employee
                                                ON users.employee_id = employee.id LEFT JOIN curators
                                              ON employee.curator_id = curators.id LEFT JOIN position_employee
                                              ON employee.job_id = position_employee.job_id
                                            WHERE responsible_task.user_id = ".$post['user_id']."
                                            GROUP BY employee.name;"
        );
        $result = $query->result();
        //array_push($result, $result[$i]);
        //}
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTaskEVPriority($post){
        $query = $this->db->query("SELECT DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period,users.id as                                                        user_id, multitask.id as task_id,points.street as points_street, points.building as points_building,points.city as points_city,
                                                multitask.full as full,  DATEDIFF(multitask.date_period, now()) as period, multitask.id as multitask_id
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                        WHERE responsible_task.user_id = " . $post['user_id'] . " AND multitask.priority = 4 AND multitask.status = 1 AND close_users != 1 AND multitask.trash != 1
                                        ORDER BY period ASC"
        );
        $result = $query->result();
        return $result;
    }

    public function getTaskEVPriorityCount($post){
        $query = $this->db->query("SELECT COUNT(multitask.id) as count_tasks
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                        WHERE responsible_task.user_id = " . $post['user_id'] . " AND multitask.priority = 4 AND multitask.status = 1 AND close_users = 0 AND multitask.trash = 0
        ");
        $result = $query->result();
        return $result;
    }

    public function getTaskHighForUsersMyTasks($post){
        $query = $this->db->query("SELECT DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period,                                                        user_id, multitask.id as task_id,points.street as points_street, points.building as points_building,points.city as points_city,
                                                multitask.full as multitask_full,  DATEDIFF(multitask.date_period, now()) as period, multitask.id as multitask_id
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                        WHERE responsible_task.user_id = " . $post['user_id'] . " AND multitask.priority = 3 AND multitask.status = 1 AND multitask.trash != 1
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTaskHighForUsers($post){
        //$count = count($post['user_id']);
        //echo $count;
        //echo "<pre>";print_r($post);exit;
        //for($i = 0;$i < $count; $i++){
        if(isset($post['select_status']) && $post['select_status'] == 1) {
            $query = $this->db->query("SELECT DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period,users.id as                                                        user_id, multitask.id as task_id,points.street as points_street, points.building as points_building,points.city as points_city,
                                                multitask.full as full,  DATEDIFF(multitask.date_period, now()) as period, multitask.id as multitask_id
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                        WHERE responsible_task.user_id = " . $post['user_id'] . " AND priority = 3 AND multitask.status = 1 AND DATEDIFF(multitask.date_period, now()) > 0
                                         AND close_users = 0
                                        ORDER BY period ASC"
            );
        } elseif(isset($post['select_status']) && $post['select_status'] == 2){
            $query = $this->db->query("SELECT DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period,users.id as                                                        user_id, multitask.id as task_id,points.street as points_street, points.building as points_building,points.city as points_city,
                                                multitask.full as full, DATEDIFF(multitask.date_period, now()) as period, multitask.id as multitask_id
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                        WHERE responsible_task.user_id = " . $post['user_id'] . " AND priority = 3 AND multitask.status = 1 AND DATEDIFF(multitask.date_period, now()) < 0 AND close_users != 1
                                        ORDER BY period ASC"
            );
        } else {
            $query = $this->db->query("SELECT DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period,users.id as                                                        user_id, multitask.id as task_id,points.street as points_street, points.building as points_building,points.city as points_city,
                                                multitask.full as full,  DATEDIFF(multitask.date_period, now()) as period, multitask.id as multitask_id
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                        WHERE responsible_task.user_id = " . $post['user_id'] . " AND priority = 3 AND multitask.status = 1 AND close_users = 0
                                        ORDER BY period ASC"
            );
        }
        $result = $query->result();
        // }
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTaskHighForUsersCount($post){
        $query =  $this->db->query("SELECT COUNT(multitask.id) as count_tasks
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = " . $post['user_id'] . " AND priority = 3 AND multitask.status = 1 AND close_users = 0 AND trash = 0
         ");
        $result = $query->result();
        return $result;
    }

    public function getTaskAverageForUsers($post){
        $count = count($post['user_id']);
        //for($i = 0;$i < $count; $i++){
        $query =  $this->db->query("SELECT users.id as user_id, multitask.id as task_id, multitask.name as task_name,multitask.date,multitask.date_period, DATEDIFF(now(),multitask.date_period) as period
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id
                                        WHERE responsible_task.user_id = ".$post['user_id']." AND priority = 2 AND multitask.trash != 1
                                        ORDER BY period ASC"
        );
        $result = $query->result();
        //}
        return $result;
    }

    public function getTaskLowForUsers($post){
        $count = count($post['user_id']);
        //for($i = 0;$i < $count; $i++){
        $query =  $this->db->query("SELECT users.id as user_id, multitask.id as task_id, multitask.name as task_name,multitask.date,multitask.date_period, DATEDIFF(now(),multitask.date_period) as period
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id
                                        WHERE responsible_task.user_id = ".$post['user_id']." AND priority = 1
                                        ORDER BY period ASC"
        );
        $result = $query->result();
        //}
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    /*
     * Раньше строка юыла - WHERE responsible_task.user_id = " . $post['user_id'] . " AND priority != 3 AND priority != 4 AND multitask.status = ".$post['select_status']."
     * Не понимаю, почему нужен (AND multitask.status = ".$post['select_status']."), хотя можно было заменить на (AND multitask.status = 1), ведь
     * $post['select_status'] не передается
     * */
    public function getTaskNoAveragetForUsers($post){
        //echo "<pre>";print_r($post);exit;
        $query = $this->db->query("SELECT DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period,users.id as user_id, multitask.id as task_id,points.street as points_street, points.building as points_building,points.city as points_city,
                                                multitask.full as full, DATEDIFF(multitask.date_period, now()) as period
                                        FROM responsible_task LEFT JOIN multitask
                                          ON responsible_task.task_id = multitask.id LEFT JOIN users
                                          ON responsible_task.user_id = users.id LEFT JOIN employee
                                          ON users.employee_id = employee.id LEFT JOIN points
                                          ON multitask.points_id = points.id
                                        WHERE responsible_task.user_id = " . $post['user_id'] . " AND priority != 3 AND priority != 4 AND multitask.status = 1
                                         AND close_users != 1 AND trash = 0
                                        ORDER BY period ASC"
        );
        $result = $query->result();
        //array_push($result, $result[$i]);
        // }
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTaskNoAverageCountForUsers($post){
        $query =  $this->db->query("SELECT COUNT(multitask.id) as count_tasks
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = " . $post['user_id'] . " AND priority != 3 AND priority != 4 AND multitask.status = 1
                                         AND close_users != 1 AND trash = 0
        ");
        $result = $query->result();
        return $result;
    }

    public function addCommentOYKD($post){
        $this->db->insert('comments_oykd', $post);
        $last_id = $this->db->insert_id();
        $query = $this->db->query("SELECT employee.surname as employee_surname, SUBSTRING(employee.name, 1, 1) AS employee_name, SUBSTRING(employee.middlename, 1, 1) AS employee_middle_name,
                                          comments_oykd.date as date
                                   FROM comments_oykd LEFT JOIN users
                                   ON comments_oykd.user_id = users.id LEFT JOIN employee
                                   ON users.employee_id = employee.id
                                   WHERE comments_oykd.id = ".$last_id."
        ");
        $result = $query->result();
        return $result;
    }

    public function getCommentOykd($task_id){
        $query =  $this->db->query("SELECT comments_oykd.*, curators.surname as curators_surname, curators.name as curators_name, curators.middle_name as curators_middle_name,
                                            employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name
                                    FROM comments_oykd LEFT JOIN users
                                      ON comments_oykd.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN curators
                                      ON employee.curator_id = curators.id
                                    WHERE task_id = {$task_id}"
        );
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getAllObjects(){
        $query =  $this->db->query("SELECT points.id as points_id, points.street as points_street, points.building as points_building,
                                            address.name as address_name, brand.text as brand_text
                                    FROM points LEFT JOIN address
                                      ON points.name_address_id = address.id LEFT JOIN brand
                                      ON points.brand_id = brand.id
                                      WHERE name_address_id != 0
        ");
        $result = $query->result();
        return $result;
    }

    public function getAllObjectsTorg(){
        $query =  $this->db->query("SELECT points.id as points_id, points.street as points_street, points.building as points_building,
                                            address.name as address_name, brand.text as brand_text
                                    FROM points LEFT JOIN address
                                      ON points.name_address_id = address.id LEFT JOIN brand
                                      ON points.brand_id = brand.id
                                      WHERE type = 1
        ");
        $result = $query->result();
        return $result;
    }

    public function getTorgObjects(){
        $query =  $this->db->query("SELECT points.id as points_id, points.street as points_street, points.building as points_building,
                                            address.name as address_name, brand.text as brand_text
                                    FROM points LEFT JOIN address
                                      ON points.name_address_id = address.id LEFT JOIN brand
                                      ON points.brand_id = brand.id
                                      WHERE type = 0
        ");
        $result = $query->result();
        return $result;
    }

    public function getAvtopark(){
        $query =  $this->db->query("SELECT *
                                    FROM avtopark
        ");
        $result = $query->result();
        return $result;
    }

    public function getEditObjects($task_id){
        $query =  $this->db->query("SELECT multitask.points_id as multitask_address_id, points.city as point_city,
                                            points.street as point_street, points.building as point_building, brand.text as brand_name
                                    FROM multitask LEFT JOIN points
                                    ON multitask.points_id = points.id LEFT JOIN brand
                                    ON points.brand_id = brand.id
                                    WHERE multitask.id = ".$task_id."
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function updateStatusClose($post, $task_id, $data, $priority){
        $this->db->update('multitask', $post, array('id' => $task_id));
        $this->db->insert('comments_task', $data);
        $this->db->select('user_id');
        $this->db->from('responsible_task');
        $this->db->where('task_id', $task_id);
        $query_res = $this->db->get();
        $result_res = $query_res->result();
        $noti_close['task_id'] = $task_id;
        $noti_close['date'] = date('Y-m-d H:i:s');
        foreach($result_res as $item){
            $noti_close['user_id'] = $item->user_id;
            $this->db->insert('noti_task_close', $noti_close);
        }
        if($priority == 4){
            $noti_close_ev['task_id'] = $task_id;
            $noti_close_ev['date'] = date('Y-m-d H:i:s');
            $this->db->insert('noti_tasks_ev', $noti_close_ev);
        }
        $query =  $this->db->query("SELECT multitask.id as mult_id, multitask.full, multitask.date as date_write, multitask.date_begin, multitask.date_period,
                                           multitask.date_close, multitask.date_perform, multitask.priority, comments_task.text, multitask.status as mult_status,
                                           points.street, points.building
                                    FROM multitask LEFT JOIN comments_task
                                      ON multitask.id = comments_task.task_id LEFT JOIN points
                                      ON multitask.points_id = points.id
                                    WHERE multitask.id = $task_id
                                    ORDER BY comments_task.id DESC LIMIT 5
        ");
        $result = $query->result();
        return $result;
    }

    /*
   * Начало печатной формы для нескольких сотрудников
   *
   */

    public function getEmployeeSelectPrint($post){
        $count = count($post['select_employee']);
        for($i = 0; $i < $count; $i++){
            $query =  $this->db->query("SELECT employee.id, employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name,
                                        curators.surname as curator_surname, curators.name as curator_name, curators.middle_name as curator_middle_name,
                                        position_employee.name as position_employee_name
                                        FROM users LEFT JOIN employee
                                        ON users.employee_id = employee.id LEFT JOIN curators
                                        ON employee.curator_id = curators.id LEFT JOIN position_employee
                                        ON users.position = position_employee.id
                                        WHERE users.id = ".$post['select_employee'][$i]."
        ");
            $result[$i] = $query->result();
        }
        return $result;
    }

    public function getTaskPriorityHigh($post){
        $count = count($post['select_employee']);
        for($i = 0; $i < $count; $i++){
            $query =  $this->db->query("SELECT multitask.id, multitask.full,
                                              DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period
                                        FROM responsible_task LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                        WHERE responsible_task.user_id = ".$post['select_employee'][$i]." AND multitask.priority = 3
        ");
            $result[$i] = $query->result();
        }
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTaskPriorityAverageAndLow($post){
        $count = count($post['select_employee']);
        for($i = 0; $i < $count; $i++){
            $query =  $this->db->query("SELECT multitask.id, multitask.full,
                                              DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period
                                        FROM responsible_task LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                        WHERE responsible_task.user_id = ".$post['select_employee'][$i]." AND multitask.priority != 3
        ");
            $result[$i] = $query->result();
        }
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    /*
   * Конец печатной формы для нескольких сотрудников
   *
   */

    public function getSelectAllDepartment(){
        $query =  $this->db->query("SELECT *
                                    FROM department
                                    WHERE dep_id != ''
        ");
        $result = $query->result();
        return $result;
    }

    /*
   * Печатная форма для отедлов
   *
   */

    public function getSelectDepartmentOnEmployee($post){
        $count = count($post['select_department']);
        for($i = 0; $i < $count; $i++){
            $query =  $this->db->query("SELECT employee.id, employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name,
                                        curators.surname as curator_surname, curators.name as curator_name, curators.middle_name as curator_middle_name,
                                        position_employee.name as position_employee_name,
                                        users.id as user_id
                                        FROM users LEFT JOIN employee
                                        ON users.employee_id = employee.id LEFT JOIN curators
                                        ON employee.curator_id = curators.id LEFT JOIN position_employee
                                        ON users.position = position_employee.id LEFT JOIN department
                                        ON employee.dep_id = department.id
                                        WHERE department.id = ".$post['select_department'][$i]." AND employee.status != 2

        ");
            $result[$i] = $query->result();
        }
        //echo "<pre>";print_r($result);exit;
        return $result;
    }


    public function getSelectDepartmentTasksHigh($new_array){
        //echo "<pre>";print_r($new_array);exit;
        $count = count($new_array);
        for($i = 0; $i < $count; $i++){
            $query =  $this->db->query("SELECT multitask.id, multitask.full,
                                              DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period
                                        FROM responsible_task LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                        WHERE responsible_task.user_id = ".$new_array[$i]." AND multitask.priority = 3
        ");
            $result[$i] = $query->result();
        }
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getSelectDepartmentTasksAverageAndLow($new_array){
        $count = count($new_array);
        for($i = 0; $i < $count; $i++){
            $query =  $this->db->query("SELECT multitask.id, multitask.full,
                                              DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period
                                        FROM responsible_task LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id
                                        WHERE responsible_task.user_id = ".$new_array[$i]." AND multitask.priority != 3
        ");
            $result[$i] = $query->result();
        }
        return $result;
    }

    /*
   * Конец печатной формы для отделов
   *
   */

    /*
   * Печатная форма отчетов для нескольких сотрудников
   *
   */

    public function getSelectEmployeeOnReport($post){
        $count = count($post['select_employee']);
        for($i = 0; $i < $count; $i++){
            $query =  $this->db->query("SELECT employee.id, employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name,
                                        curators.surname as curator_surname, curators.name as curator_name, curators.middle_name as curator_middle_name,
                                        position_employee.name as position_employee_name
                                        FROM users LEFT JOIN employee
                                        ON users.employee_id = employee.id LEFT JOIN curators
                                        ON employee.curator_id = curators.id LEFT JOIN position_employee
                                        ON users.position = position_employee.id
                                        WHERE users.id = ".$post['select_employee'][$i]."
        ");
            $result[$i] = $query->result();
        }
        return $result;
    }

    public function getTaskReport($post){
        $count = count($post['select_employee']);
        for($i = 0; $i < $count; $i++){
            $query =  $this->db->query("SELECT multitask.id, multitask.full,
                                              DATE_FORMAT(multitask.date,'%d.%m.%y') as date,  DATE_FORMAT(multitask.date_period,'%d.%m.%y') as date_period,
                                              comments_oykd.comment as comments_oykd_comment, DATEDIFF(multitask.date_period,now()) as period
                                        FROM responsible_task LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id LEFT JOIN comments_oykd
                                        ON multitask.id = comments_oykd.task_id
                                        WHERE responsible_task.user_id = ".$post['select_employee'][$i]."
        ");
            $result[$i] = $query->result();
        }
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    /*
   * Конец Печатной формы отчетов для нескольких сотрудников
   *
   */

    public function getTasksByDepartment($post){
        $query_tasks = $this->db->query("SELECT multitask.*, LEFT(multitask.full,130) as multitask_full,
                                                responsible_task.who_insert_id,
                                                users.id as employee_user_id,
                                                employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                                DATEDIFF(multitask.date_period, now()) as period, DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period,
                                                SUBSTRING(employee.name, 1, 1) AS employee_name, SUBSTRING(employee.middlename, 1, 1) AS employee_middle_name
                                        FROM users LEFT JOIN responsible_task
                                            ON users.id = responsible_task.user_id LEFT JOIN multitask
                                            ON responsible_task.task_id = multitask.id LEFT JOIN employee
                                            ON users.employee_id = employee.id LEFT JOIN department
                                            ON  employee.dep_id = department.id
                                        WHERE department.id = ".$post['department']." AND multitask.moder = ".$post['moder']."
                                        GROUP BY multitask.id
                                        ORDER BY multitask.priority DESC, period DESC"
        );
        $tasks = $query_tasks->result();
        //echo "<pre>";print_r($tasks);exit;
        return $tasks;
    }

    public function searchTaskBd($post){
        $query = $this->db->query("SELECT  multitask.*, LEFT(multitask.full,130) as multitask_full,
                                                responsible_task.who_insert_id,
                                                users.id as employee_user_id,
                                                employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                                DATEDIFF(multitask.date_period, now()) as period, DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period,
                                                SUBSTRING(employee.name, 1, 1) AS employee_name, SUBSTRING(employee.middlename, 1, 1) AS employee_middle_name,
                                                 DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                                 elect_tasks.user_id as elect_task_user_id,
                                                 address.name as adr_name, address.address as adr_location
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN points
                                      ON multitask.points_id = points.id LEFT JOIN address
                                      ON points.name_address_id = address.id LEFT JOIN elect_tasks
                                      ON multitask.id = elect_tasks.task_id
                                    WHERE (multitask.full LIKE '%".$post['text']."%' OR employee.surname LIKE '%".$post['text']."%' 
                                    OR employee.name LIKE '%".$post['text']."%' OR employee.middlename LIKE '%".$post['text']."%' 
                                    OR multitask.id LIKE '%".$post['text']."%' OR address.name LIKE '%".$post['text']."%' OR address.address LIKE '%".$post['text']."%')  
                                    AND multitask.trash != 1
                                    ORDER BY multitask.priority DESC, period DESC
        ");
        $tasks = $query->result();
        //echo "<pre>";print_r($tasks);exit;
        return $tasks;
    }

    public function searchMyTaskBd($post){
        $query = ("SELECT  multitask.*, LEFT(multitask.full,130) as multitask_full,
                                                responsible_task.who_insert_id,
                                                users.id as employee_user_id,
                                                employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                                DATEDIFF(multitask.date_period, now()) as period, DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period,
                                                SUBSTRING(employee.name, 1, 1) AS employee_name, SUBSTRING(employee.middlename, 1, 1) AS employee_middle_name,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id
        ");
        if($post['type'] == 'vx'){
            $query .= (" WHERE (multitask.full LIKE '%".$post['text']."%' OR employee.surname LIKE '%".$post['text']."%' OR employee.name LIKE '%".$post['text']."%' OR employee.middlename LIKE '%".$post['text']."%' OR multitask.id LIKE '%".$post['text']."%') AND multitask.trash != 1
                                    AND responsible_task.user_id = ".$post['user_id']."
                                    ORDER BY multitask.priority DESC, period DESC");
        } elseif($post['type'] == 'isx'){
            $query .= (" WHERE (multitask.full LIKE '%".$post['text']."%' OR employee.surname LIKE '%".$post['text']."%' OR employee.name LIKE '%".$post['text']."%' OR employee.middlename LIKE '%".$post['text']."%' OR multitask.id LIKE '%".$post['text']."%') AND multitask.trash != 1
                                    AND responsible_task.who_insert_id = ".$post['user_id']."
                                    ORDER BY multitask.priority DESC, period DESC");
        }
        $q = $this->db->query($query);
        $tasks = $q->result();
        return $tasks;
    }

    public function deleteOrReestablishTask($task_id, $data){
        $this->db->update('multitask', $data, array('id' => $task_id));
        /*$this->db->delete('multitask', array('id' => $task_id));
        $this->db->delete('responsible_task', array('task_id' => $task_id));
        $this->db->delete('comments_oykd', array('task_id' => $task_id));
        $this->db->delete('comments_task', array('task_id' => $task_id));
        $this->db->delete('reminders_tasks', array('task_id' => $task_id));*/
        return true;
    }

    public function remindersUsers($user_id){
        $query = $this->db->query("SELECT id
                                   FROM reminders_tasks
                                   WHERE user_id = ".$user_id."
        ");
        $result = $query->num_rows();
        return $result;
    }

    public function taskRemindersUser($user_id){
        $this->db->select("multitask.full as multitask_full, reminders_tasks.task_id as task_id, reminders_tasks.date as date");
        $this->db->from("reminders_tasks");
        $this->db->join("multitask", "reminders_tasks.task_id = multitask.id", "inner");
        $this->db->where("reminders_tasks.user_id", $user_id);
        $query = $this->db->get();
        /*$query = $this->db->query("SELECT LEFT(multitask.full,70) as multitask_full, reminders_tasks.task_id as task_id, DATE_FORMAT(reminders_tasks.date,'%d.%m.%y') as date
                                   FROM reminders_tasks LEFT JOIN multitask
                                   ON reminders_tasks.task_id = multitask.id
                                   WHERE reminders_tasks.user_id = ".$user_id."
        ");*/
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function deleteReminderTaskUser($task_id, $user_id){
        $this->db->select('notice_tasks_comment.user_id as user_comm, notice_tasks_comment.comment_id');
        $this->db->from('notice_tasks_comment');
        $this->db->join('comments_task', 'comments_task.id = notice_tasks_comment.comment_id');
        $this->db->where('comments_task.task_id', $task_id);
        $this->db->where('notice_tasks_comment.user_id', $user_id);
        //$this->db->group_by('notice_tasks_comment.comment_id');
        $query = $this->db->get();
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        if(isset($result) && !empty($result)){
            foreach($result as $item){
                $this->db->delete('notice_tasks_comment', array('comment_id' => $item->comment_id, 'user_id' => $user_id));
            }
        }
        $this->db->select('user_id, who_insert_id');
        $this->db->from('responsible_task');
        $this->db->where('task_id', $task_id);
        $this->db->where('user_id', $user_id);
        $query = $this->db->get();
        $result = $query->result();
        if(isset($result[0]) && !empty($result[0])){
            if($result[0]->user_id == USER_COOKIE_ID && $result[0]->who_insert_id == USER_COOKIE_ID){
                $data['change_content'] = 0;
                $data['change_content_out'] = 0;
            } elseif($result[0]->user_id == USER_COOKIE_ID){
                $data['change_content'] = 0;
            } elseif($result[0]->who_insert_id == USER_COOKIE_ID){
                $data['change_content_out'] = 0;
            }
        }
        //echo "<pre>";print_r($result);exit;
        $this->updateReviewCommentTaskAnswer($task_id, $user_id);
        $this->db->delete('reminders_tasks', array('task_id' => $task_id, 'user_id' => $user_id));
        $data['declaim'] = 1;
        $this->db->update('responsible_task', $data, array('task_id' => $task_id, 'user_id' => USER_COOKIE_ID));
        $this->reviewTasksLog($task_id, $user_id);
        return true;
    }

    public function deleteNotificationOykd($comment_id, $user_id){

        $this->db->select('noti_comment_user.comment_id');
        $this->db->from('noti_comment_user');
        $this->db->join('comments_task', 'comments_task.id = noti_comment_user.comment_id');
        $this->db->where('noti_comment_user.comment_id', $comment_id);
        $this->db->where('noti_comment_user.user_id', $user_id);
        $query = $this->db->get();
        $result_two = $query->result();
        //echo "<pre>";print_r($result_two);exit;
        if(isset($result_two) && !empty($result)){
            foreach ($result_two as $item) {
                $this->db->delete('noti_comment_user', array('comment_id' => $item->comment_id, 'user_id' => $user_id));
            }

        }

        $this->db->select('comments_task.task_id, notice_tasks_comment.user_id as user_com, notice_tasks_comment.comment_id');
        $this->db->from('notice_tasks_comment');
        $this->db->join('comments_task', 'comments_task.id = notice_tasks_comment.comment_id');
        $this->db->where('notice_tasks_comment.comment_id', $comment_id);
        $this->db->where('notice_tasks_comment.user_id', $user_id);
        $query = $this->db->get();
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        if(isset($result[0]) && !empty($result[0])){
            $this->db->delete('notice_tasks_comment', array('comment_id' => $result[0]->comment_id, 'user_id' => $user_id));
            $this->updateReviewCommentTaskAnswer($result[0]->task_id, $user_id);
            $this->reviewTasksLog($result[0]->task_id, $user_id);
        }
        return true;
    }

    public function deleteNotificationOykdAll($user_id){
        $this->db->delete('notice_tasks_comment', array('user_id' => $user_id));
        return true;
    }

    public function closeDirectorTasks($post, $task_id, $comment){
        //echo "<pre>";print_r($post);exit;
        $this->db->update('multitask', $post, array('id' => $task_id));
        $data['close_users'] = 0;
        $this->db->update('responsible_task', $data, array('task_id' => $task_id));
        $this->db->insert('comments_task', $comment);
        return true;
    }

    public function getSelectEmployee($department_id){
        $query = $this->db->query("SELECT users.id as user_id, employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name
                                    FROM employee LEFT JOIN users
                                    ON employee.id = users.employee_id
                                    WHERE department = ".$department_id." AND employee.status != 2;
        ");
        $result = $query->result();
        return $result;
    }

    public function countNoticeUsersTasks($user_id){
        $query = $this->db->get_where('notice_tasks_comment', array('user_id' => $user_id));
        $result = $query->num_rows();
        return $result;
    }

    public function noticeUsersTasks($user_id){
        $query = $this->db->query("SELECT notice.user_id as any_user, notice.comment_id as comment_id, DATE_FORMAT(com_ts.date,'%d.%m.%y') as date,
                                          LEFT(com_ts.text, 70) as text, com_ts.task_id as task_id
                                   FROM notice_tasks_comment as notice LEFT JOIN comments_task as com_ts
                                   ON notice.comment_id = com_ts.id
                                    WHERE notice.user_id = ".$user_id."
                                    ORDER BY notice.id DESC
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getEmployees(){
        $query = $this->db->query("SELECT users.id as user_id, employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name
                                   FROM users LEFT JOIN employee
                                   ON users.employee_id = employee.id
                                   WHERE employee.status != 2
                                   ORDER BY employee.surname ASC
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTaskForNotification($task_id){
        $query = $this->db->query("SELECT multitask.id as multitask_id, LEFT(multitask.full,15) as multitask_full, users.telegram_id as telegram_id,
                                         DATE_FORMAT(multitask.date_period,'%d.%m.%y %H:%i') as date_period, employee.phone, users.id as user_id,
                                         employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, 
                                                 LEFT(employee.middlename,1) as employee_middle_name, multitask.full as full_text,
                                                 points.street, points.building, multitask.status as mult_status,
                                                 multitask.date_period as date_pp, multitask.date_begin as date_b, multitask.date as date_nn
                                         FROM multitask LEFT JOIN responsible_task
                                         ON multitask.id = responsible_task.task_id LEFT JOIN users
                                         ON responsible_task.user_id = users.id LEFT JOIN employee
                                         ON users.employee_id = employee.id LEFT JOIN points
                                         ON multitask.points_id = points.id
                                         WHERE multitask.id = ".$task_id."
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function updateDeclaim($task_id, $data, $user_id){
        $this->db->update('responsible_task', $data, array('task_id' => $task_id, 'user_id' => $user_id));
        $this->db->delete('reminders_tasks', array('task_id' => $task_id, 'user_id' => $user_id));
        return true;
    }

    public function updateStatusCloseTaskUsers($task_id, $data, $post, $close_user, $user_id){
       //echo "<pre>";print_r($post);exit;
        $this->db->update('multitask', $data, array('id' => $task_id));
        if($data['status'] == 2){
            $this->db->update('responsible_task', $close_user, array('task_id' => $task_id, 'user_id' => $user_id));
        } else {
            $this->db->update('responsible_task', $close_user, array('task_id' => $task_id));
        }
        if(isset($post['text']) && !empty($post['text'])){
            $this->db->insert('comments_task', $post);
            $last_id = $this->db->insert_id();
            $query = $this->db->query("SELECT com.id,com.task_id,com.user_id,com.text, com.date, em.name,em.surname,em.middlename,us.login,com.files,
                                              mult.full, mult.id as mult_id, priority, mult.date_begin, mult.date_period, mult.status as mult_status,
                                              points.street, points.building, mult.date
                                        FROM comments_task as com LEFT JOIN users as us
                                          ON  com.user_id = us.id LEFT JOIN employee as em
                                          ON us.employee_id = em.id LEFT JOIN multitask as mult
                                          ON com.task_id = mult.id LEFT JOIN points
                                          ON mult.points_id = points.id
                                        WHERE com.id = ".$last_id."
            ");
            $result = $query->result();
            return $result;
        } else {
            $query = $this->db->query("SELECT multitask.full, multitask.id as mult_id, priority
                                        FROM multitask
                                        WHERE id = ".$task_id."
            ");
            $result = $query->result();
            return $result;
        }
    }

    public function getNotiNewOykd(){
        $query = $this->db->query("SELECT multitask.*, LEFT(multitask.full,15) as multitask_full
                                    FROM multitask
                                    WHERE status != 3 AND trash != 1
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getAwatingCloseTasks($user_id){
        $query = $this->db->query("SELECT multitask.*, LEFT(multitask.full,70) as multitask_full
                                    FROM multitask LEFT JOIN responsible_task
                                    ON multitask.id = responsible_task.task_id
                                    WHERE responsible_task.who_insert_id = ".$user_id." AND status = 2 AND trash != 1
                                    GROUP BY multitask_full
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getAwatingCloseTasksForOykd(){
        $query = $this->db->query("SELECT multitask.*,responsible_task.who_insert_id, 
                                          employee.surname as employee_surname, employee.name as employee_name,employee.middlename as employee_middle_name,
                                          LEFT(multitask.full,60) as multitask_full,DATEDIFF(multitask.date_period, now()) as period
                                    FROM multitask LEFT JOIN responsible_task
                                    ON multitask.id = responsible_task.task_id LEFT JOIN users
                                    ON responsible_task.user_id = users.id LEFT JOIN employee
                                    ON users.employee_id = employee.id
                                    WHERE close_users = 1 AND multitask.status != 3 AND multitask.status != 5 AND trash != 1
                                     ORDER BY  multitask.priority DESC, period ASC
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function countAwatingCloseTasks($user_id){
        $query = $this->db->query("SELECT multitask.id
                                    FROM multitask LEFT JOIN responsible_task
                                    ON multitask.id = responsible_task.task_id
                                    WHERE responsible_task.who_insert_id = ".$user_id." AND status = 2 AND trash != 1
                                    GROUP BY multitask.full
        ");
        $result = $query->num_rows();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function addRemindersForEmployeeForTasks($post){
        $this->db->insert('notes_task', $post);
        $last_id = $this->db->insert_id();
        $query = $this->db->query("SELECT notes_task.text as text, DATE_FORMAT(notes_task.date,'%d.%m.%Y %H:%i') as date,
                                    employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name
                                    FROM notes_task LEFT JOIN multitask
                                    ON notes_task.task_id = multitask.id LEFT JOIN users
                                    ON notes_task.user_id = users.id LEFT JOIN employee
                                    ON users.employee_id = employee.id
                                    WHERE notes_task.id = ".$last_id."
        ");
        $result = $query->result();
        return $result;
    }

    public function getallNotesForTask($task_id){
        $query = $this->db->query("SELECT notes_task.text as text, DATE_FORMAT(notes_task.date,'%d.%m.%Y %H:%i') as date,
                                    employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name
                                    FROM notes_task LEFT JOIN multitask
                                    ON notes_task.task_id = multitask.id LEFT JOIN users
                                    ON notes_task.user_id = users.id LEFT JOIN employee
                                    ON users.employee_id = employee.id
                                    WHERE notes_task.task_id = ".$task_id."
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function updateChangeContent($task_id, $status_change_text,$user_id){
        $this->db->update('responsible_task', $status_change_text, array('task_id' => $task_id, 'user_id' => $user_id));
        return true;
    }

    public function updateChangeContentWhoInsert($task_id, $status_change_text,$user_id){
        $this->db->update('responsible_task', $status_change_text, array('task_id' => $task_id, 'who_insert_id' => $user_id));
        return true;
    }

    public function getSelectExecutor($post, $user_id){
        $query = $this->db->query("SELECT multitask.status,responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.* ,
                                                DATEDIFF(multitask.date_period, now()) as period, responsible_task.change_content, responsible_task.change_content_out,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                                 employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, 
                                                 LEFT(employee.middlename,1) as employee_middle_name,
                                                  address.name as adr_name, address.address as adr_location
                                   FROM responsible_task LEFT JOIN users
                                   ON responsible_task.user_id = users.id LEFT JOIN multitask
                                   ON responsible_task.task_id = multitask.id LEFT JOIN employee
                                   ON users.employee_id = employee.id LEFT JOIN points
                                    ON multitask.points_id = points.id LEFT JOIN brand
                                    ON points.brand_id = brand.id LEFT JOIN address
                                     ON points.name_address_id = address.id
                                   WHERE responsible_task.user_id = ".$post['user_id']." AND responsible_task.who_insert_id = ".$user_id." AND multitask.id IS NOT NULL
                                   AND multitask.status != 3 AND multitask.status != 4 AND multitask.status != 2 AND multitask.trash != 1
                                   ORDER BY multitask.priority DESC, period ASC

        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getSelectExecutorMyTasks($post, $user_id){
        $query = $this->db->query("SELECT multitask.full as multitask_full, multitask.id as id, DATEDIFF(multitask.date_period, now()) as period,
                                    DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period, users.id as user_id, multitask.priority as priority
                                   FROM responsible_task LEFT JOIN users
                                   ON responsible_task.user_id = users.id LEFT JOIN multitask
                                   ON responsible_task.task_id = multitask.id
                                   WHERE responsible_task.user_id = ".$user_id." AND responsible_task.who_insert_id = ".$post['user_id']." AND multitask.id IS NOT NULL
                                   AND multitask.status != 3 AND multitask.status != 4 AND multitask.status != 2 AND multitask.trash != 1
                                   ORDER BY multitask.priority DESC, period ASC

        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getSelectExecutorAllTask($post, $user_id){
        $query = $this->db->query("SELECT multitask.full as multitask_full, multitask.id as id, DATEDIFF(multitask.date_period, now()) as period,
                                    DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period, users.id as user_id, multitask.priority as priority
                                   FROM responsible_task LEFT JOIN users
                                   ON responsible_task.user_id = users.id LEFT JOIN multitask
                                   ON responsible_task.task_id = multitask.id
                                   WHERE responsible_task.who_insert_id = ".$post['user_id']." AND multitask.id IS NOT NULL
                                   ORDER BY multitask.priority DESC, period ASC

        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getSelectObjectForSortTasks($post){
        $query = $this->db->query("SELECT address.id, points.*,
                                     multitask.*, LEFT(multitask.full,130) as multitask_full,
                                                DATEDIFF(multitask.date_period, now()) as period,DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period
                                    FROM multitask LEFT JOIN address
                                      ON multitask.points_id = address.id LEFT JOIN points
                                      ON address.id = points.name_address_id
                                    WHERE address.id = ".$post['object']." AND multitask.id IS NOT NULL;

        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getObjectsForSort(){
        $query = $this->db->query("SELECT points.id as id, points.street, points.building, address.name as address_name
                                    FROM points LEFT JOIN address
                                      ON points.name_address_id = address.id LEFT JOIN brand
                                      ON points.brand_id = brand.id
                                      WHERE name_address_id != 0
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    /*
     *
     * Уведомлялка для ОУКД о задачах, которые ждут модерации
     *
     * */

    public function countAllAwatingModerForOYKD(){
        $query = $this->db->query("SELECT multitask.id
                                    FROM multitask
                                    WHERE status != 3 AND trash != 1
        ");
        $result = $query->num_rows();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getAllAwatingModerForOYKD(){
        $query = $this->db->query("SELECT multitask.*, LEFT(multitask.full,15) as multitask_full
                                    FROM multitask
                                    WHERE status != 3 AND trash != 1
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    /*
     * Конец уведомлялки
     * */

    public function getWhoInsert(){
        $query = $this->db->query("SELECT responsible_task.task_id as task_id, employee.surname as who_surname, employee.name as who_name, employee.middlename as who_middlename
                                    FROM  responsible_task, users, employee
                                    WHERE responsible_task.who_insert_id = users.id AND users.employee_id = employee.id  
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        foreach($result as $t){
            $data['task_id'] = $t->task_id;
            $data['who_surname'] = $t->who_surname;
            $data['who_name'] = $t->who_name;
            $data['who_middlename'] = $t->who_middlename;
            $this->db->insert('who_insert', $data);
        }

        return true;
    }

    public function getTasksMyDepartment($department_id){
        //print_r($department_id);exit;
        $query = $this->db->query("SELECT department.id as depy_id, employee.dep_id, multitask.*, LEFT(multitask.full,130) as multitask_full,
                                                responsible_task.who_insert_id, responsible_task.date_declaim, responsible_task.declaim,
                                                users.id as employee_user_id,
                                                LEFT(employee.name,1) as employee_name, employee.surname as employee_surname, LEFT(employee.middlename,1) as employee_middle_name,
                                                DATEDIFF(multitask.date_period, now()) as period,DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period,
                                                who_insert.who_surname as who_insert_surname, LEFT(who_insert.who_name,1) as who_insert_name, LEFT(who_insert.who_middlename,1) as who_insert_middlename,
                                                COUNT(multitask.id) as count_m_id
                                    FROM responsible_task, multitask, users, employee,department, who_insert
                                      WHERE responsible_task.task_id = multitask.id AND responsible_task.user_id = users.id
                                      AND users.employee_id = employee.id AND employee.dep_id = department.dep_id
                                            AND responsible_task.task_id = who_insert.task_id
                                      AND department.id = ".$department_id." AND multitask.status = 1  AND multitask.trash != 1
                                    GROUP BY multitask.id
                                    ORDER BY multitask.priority DESC, period ASC;

        ");
        $tasks = $query->result();
        //echo "<pre>";print_r($tasks);exit;
        return $tasks;
    }

    public function getTasksMyDepartmentCount($department_id){
        //print_r($department_id);exit;
        $query = $this->db->query("SELECT multitask.id
                                          FROM  multitask LEFT JOIN responsible_task
                                         ON responsible_task.task_id = multitask.id LEFT JOIN users
                                         ON responsible_task.user_id = users.id LEFT JOIN employee
                                         ON users.employee_id = employee.id LEFT JOIN department
                                         ON employee.dep_id = department.dep_id
                                        WHERE department.id = ".$department_id." AND multitask.status = 1  AND multitask.trash != 1

        ");
        $tasks = $query->num_rows();
        //echo "<pre>";print_r($tasks);exit;
        return $tasks;
    }

    public function noticeCloseTasksForOYKD($task_id, $data){
        $this->db->update('multitask', $data, array('id' => $task_id));
        return true;
    }

    public function getBrand(){
        $query = $this->db->get('brand');
        $result = $query->result();
        return $result;
    }

    public function getSelectByBrand($post){
        $query = $this->db->query("SELECT employee.dep_id, multitask.*, LEFT(multitask.full,130) as multitask_full,
                                                responsible_task.who_insert_id,
                                                users.id as employee_user_id,
                                                employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                                DATEDIFF(multitask.date_period, now()) as period,DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period
                                   FROM multitask LEFT JOIN points
                                   ON multitask.points_id = points.id LEFT JOIN brand
                                   ON points.brand_id = brand.id LEFT JOIN responsible_task
                                   ON multitask.id = responsible_task.id LEFT JOIN users
                                   ON responsible_task.user_id = users.id LEFT JOIN employee
                                   ON users.employee_id = employee.id
                                   WHERE brand.id = ".$post['brand']." AND multitask.status != 3 AND multitask.status != 5 AND multitask.trash != 1 AND multitask.close_users != 1
                                    ORDER BY responsible_task.declaim ASC,multitask.change_content DESC,  multitask.priority DESC, period DESC

        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function addDateReminder($post, $task_id){
        $this->db->update('multitask', $post, array('id' => $task_id));
        return true;
    }

    public function getTaskAll(){
        $query = $this->db->query("SELECT employee.dep_id, multitask.*, LEFT(multitask.full,130) as multitask_full,
                                            responsible_task.who_insert_id,responsible_task.date_declaim as date_declaim, responsible_task.declaim as declaim,
                                            users.id as employee_user_id, responsible_task.date_declaim as date_declaim, 
                                            DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                            employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                            DATEDIFF(multitask.date_period, now()) as period,DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period
                                FROM responsible_task LEFT JOIN users
                                  ON responsible_task.user_id = users.id LEFT JOIN employee
                                  ON users.employee_id = employee.id LEFT JOIN curators
                                  ON employee.curator_id = curators.id LEFT JOIN multitask
                                  ON responsible_task.task_id = multitask.id LEFT JOIN points
                                  ON multitask.points_id = points.id LEFT JOIN brand
                                  ON points.brand_id = brand.id LEFT JOIN address
                                  ON points.name_address_id = address.id
                                WHERE multitask.trash != 1 AND multitask.status != 3 AND multitask.status != 4
                                ORDER BY multitask.priority DESC, date_perform_users ASC, multitask.id LIMIT 50
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }


    /*
     * Новая версия выборки для раздела "все задачи"
     * */
    public function getTasksAllUsers($post, $load){
        //echo "<pre>";print_r($post);echo "</pre>";exit;
        $this->db->select('multitask.id as id, multitask.date_period as period, multitask.full as multitask_full, multitask.status as status, multitask.priority, multitask.date_close, multitask.date_perform, multitask.date, multitask.date_begin');
        $this->db->select('multitask.date_period as date_period');
        $this->db->select('e1.name as employee_name, e1.surname as employee_surname, e1.middlename as employee_middle_name');
        $this->db->select('e2.surname as who_insert_surname, e2.name as who_insert_name, e2.middlename as who_insert_middlename');
        $this->db->select('address.name as adr_name, address.address as adr_location');

        $this->db->from('responsible_task');

        $this->db->join('multitask', 'responsible_task.task_id = multitask.id', 'left');
        $this->db->join('users as us1', 'responsible_task.user_id = us1.id', 'inner');
        $this->db->join('employee as e1', 'us1.employee_id = e1.id', 'inner');
        $this->db->join('points', 'multitask.points_id = points.id', 'left');
        if(!empty($post['brand'])) {
            $this->db->join('brand', 'points.brand_id = brand.id', 'left');
        }
        $this->db->join('address', 'points.name_address_id = address.id', 'left');
        $this->db->join('department', 'e1.dep_id = department.dep_id', 'inner');
        $this->db->join('users us2', 'responsible_task.who_insert_id = us2.id', 'inner');
        $this->db->join('employee e2', 'us2.employee_id = e2.id', 'inner');
        $this->db->join('elect_tasks', 'multitask.id = elect_tasks.task_id', 'left');
        if(!empty($post['komentator'])) {
            $this->db->join('comments_task', 'multitask.id = comments_task.task_id', 'left');
        }

        if(!empty($post['department'])){
            $this->db->where('department.id', $post['department']);
        }
        if(!empty($post['employee'])){
            $this->db->where('responsible_task.user_id', $post['employee']);
        }
        if(!empty($post['status'])){
            $this->db->where('multitask.status', $post['status']);
        }
        if(!empty($post['executor'])){
            $this->db->where('responsible_task.who_insert_id', $post['executor']);
        }
        if(!empty($post['komentator'])){
            $this->db->where('comments_task.user_id', $post['komentator']);
        }
        if(!empty($post['object'])){
            $this->db->where('multitask.points_id', $post['object']);
        }
        if(!empty($post['brand'])){
            $this->db->where('brand.id', $post['brand']);
        }
        if(!empty($post['priority'])){
            $this->db->where('multitask.priority', $post['priority']);
        }
        if(isset($post['from']) && $post != '1970-01-01'){
            $this->db->where('multitask.date >', $post['from']);
        }
        if(isset($post['to']) &&$post['to'] != '1970-01-01'){
            $this->db->where('multitask.date <', $post['to']);
        }
        if(!empty($post['search'])){
            $like_s = "(multitask.id LIKE '%".$post['search']."%' OR multitask.full LIKE '%".$post['search']."%')";
            $this->db->where($like_s);
            //$this->db->like('(multitask.id', $post['search']);
            //$this->db->or_like('multitask.ful)', $post['search']);
        }
        if(empty($post['department'] && empty($post['employee']) && empty($post['status']) && empty($post['executor']) && empty($post['object']) && empty($post['brand']) && empty($post['priority']))){
            //$this->db->where('multitask.status !=', 3);
            //$this->db->where('multitask.status !=', 4);
        }
        $this->db->where('multitask.trash', 0);
        $this->db->group_by("multitask.id");
        $this->db->order_by('multitask.priority', 'DESC');
        $this->db->order_by('multitask.date_period', 'ASC');
        $this->db->limit(COUNT_LOAD_TASKS, COUNT_LOAD_TASKS*$load['load']);


        $query = $this->db->get();
        //echo "<pre>";print_r($s);exit;
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }


    // Count Задач с выборкой или без в разделе "все задания"
    public function countGetTasksAllUsers($post, $load){
        $this->db->select('multitask.id as id');

        $this->db->from('responsible_task');

        $this->db->join('multitask', 'responsible_task.task_id = multitask.id', 'left');
        $this->db->join('users as us1', 'responsible_task.user_id = us1.id', 'inner');
        $this->db->join('employee as e1', 'us1.employee_id = e1.id', 'inner');
        $this->db->join('points', 'multitask.points_id = points.id', 'left');
        if(!empty($post['brand'])) {
            $this->db->join('brand', 'points.brand_id = brand.id', 'left');
        }
        $this->db->join('address', 'points.name_address_id = address.id', 'left');
        $this->db->join('department', 'e1.dep_id = department.dep_id', 'inner');
        $this->db->join('users us2', 'responsible_task.who_insert_id = us2.id', 'inner');
        $this->db->join('employee e2', 'us2.employee_id = e2.id', 'inner');
        $this->db->join('elect_tasks', 'multitask.id = elect_tasks.task_id', 'left');
        if(!empty($post['komentator'])) {
            $this->db->join('comments_task', 'multitask.id = comments_task.task_id', 'left');
        }

        if(!empty($post['department'])){
            $this->db->where('department.id', $post['department']);
        }
        if(!empty($post['employee'])){
            $this->db->where('responsible_task.user_id', $post['employee']);
        }
        if(!empty($post['status'])){
            $this->db->where('multitask.status', $post['status']);
        }
        if(!empty($post['executor'])){
            $this->db->where('responsible_task.who_insert_id', $post['executor']);
        }
        if(!empty($post['komentator'])){
            $this->db->where('comments_task.user_id', $post['komentator']);
        }
        if(!empty($post['object'])){
            $this->db->where('multitask.points_id', $post['object']);
        }
        if(!empty($post['brand'])){
            $this->db->where('brand.id', $post['brand']);
        }
        if(!empty($post['priority'])){
            $this->db->where('multitask.priority', $post['priority']);
        }
        if(isset($post['from']) && $post != '1970-01-01'){
            $this->db->where('multitask.date >', $post['from']);
        }
        if(isset($post['to']) &&$post['to'] != '1970-01-01'){
            $this->db->where('multitask.date <', $post['to']);
        }
        if(!empty($post['search'])){
            $like_s = "(multitask.id LIKE '%".$post['search']."%' OR multitask.full LIKE '%".$post['search']."%')";
            $this->db->where($like_s);
            //$this->db->like('(multitask.id', $post['search']);
            //$this->db->or_like('multitask.ful)', $post['search']);
        }
        if(empty($post['department'] && empty($post['employee']) && empty($post['status']) && empty($post['executor']) && empty($post['object']) && empty($post['brand']) && empty($post['priority']))){
            //$this->db->where('multitask.status !=', 3);
            //$this->db->where('multitask.status !=', 4);
        }
        $this->db->where('multitask.trash', 0);
        $this->db->group_by("multitask.id");
        //$this->db->limit(10);

        $query = $this->db->get();
        $result = $query->num_rows();
        //$result = $query->num_;
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function updateAllTasksActive(){
        $data['declaim'] = 0;
        $query = $this->db->query("SELECT multitask.id,responsible_task.declaim
                                   FROM multitask LEFT JOIN responsible_task
                                   ON multitask.id = responsible_task.id
                                   WHERE status != 3 AND status != 4 AND status != 5 AND moder != 1 AND moder != 3
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        foreach($result as $item){
            $this->db->update('multitask',$data, array('id' => $item->id));
        }
        exit('Скрипт выполнен');
    }

    public function checkViewTaskMy($task_id, $user_id){
        $query = $this->db->query("SELECT responsible_task.*
                                   FROM responsible_task LEFT JOIN users
                                   ON responsible_task.user_id = users.id
                                   WHERE responsible_task.user_id = ".$user_id." AND responsible_task.task_id = ".$task_id."
        ");
        $tasks = $query->result();
        return $tasks;
    }

    // Кнопка "Загрузить еще". Старая версия
    public function getSelectAllTasksNumber($number, $number_count){
        $query = $this->db->query("SELECT employee.dep_id, multitask.*, LEFT(multitask.full,130) as multitask_full,
                                                responsible_task.who_insert_id,
                                                users.id as employee_user_id,
                                                employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                                DATEDIFF(multitask.date_period, now()) as period,DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period
                                    FROM responsible_task LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN curators
                                      ON employee.curator_id = curators.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id LEFT JOIN points
                                      ON multitask.points_id = points.id LEFT JOIN brand
                                      ON points.brand_id = brand.id LEFT JOIN address
                                      ON points.name_address_id = address.id
                                    WHERE multitask.trash != 1 AND multitask.status != 3 AND multitask.status != 5
                                    ORDER BY multitask.priority DESC, period ASC, multitask.id LIMIT ".$number.",".$number_count."
        ");
        $result = $query->result();
        return $result;
    }

    public function getTaskExportToExcel($user_id){
        $query = $this->db->query("SELECT multitask.id as multitask_id, multitask.full as multitask_full, multitask.date_period multitask_date_period,
                                          multitask.date_begin, multitask.date_close, multitask.date as multitask_date, multitask.date_begin,
                                          multitask.priority, multitask.status as multitask_status,  
                                          e2.surname as employee_surname, e2.name as employee_name, e2.middlename as employee_middle_name
                                    FROM responsible_task LEFT JOIN users u1
                                      ON responsible_task.user_id = u1.id LEFT JOIN employee e1
                                      ON u1.employee_id = e1.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id LEFT JOIN points
                                      ON multitask.points_id = points.id LEFT JOIN brand
                                      ON points.brand_id = brand.id LEFT JOIN address
                                      ON points.name_address_id = address.id LEFT JOIN users u2
                                      ON responsible_task.who_insert_id = u2.id LEFT JOIN employee e2
                                      ON u2.employee_id = e2.id
                                    WHERE multitask.trash != 1 AND u1.id = ".$user_id."
                                    GROUP BY responsible_task.task_id;"
        );
        $result = $query->result();
        return $result;
    }

    public function getTaskDirectorClose(){
        $query = $this->db->query("SELECT employee.dep_id, multitask.*, LEFT(multitask.full,130) as multitask_full,
                                                responsible_task.who_insert_id, responsible_task.date_declaim as date_declaim, responsible_task.declaim as declaim,
                                                users.id as employee_user_id,
                                                employee.name as employee_name, employee.surname as employee_surname, employee.middlename as employee_middle_name,
                                                DATEDIFF(multitask.date_period, now()) as period,DATE_FORMAT(multitask.date_period,'%d.%m.%Y %H:%i') as date_period
                                    FROM responsible_task LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN curators
                                      ON employee.curator_id = curators.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id LEFT JOIN points
                                      ON multitask.points_id = points.id LEFT JOIN brand
                                      ON points.brand_id = brand.id LEFT JOIN address
                                      ON points.name_address_id = address.id
                                    WHERE multitask.trash != 1 AND multitask.status = 3 AND multitask.priority = 4 OR (responsible_task.who_insert_id = ".DIRECTOR_ID." AND multitask.status = 3) 
                                    ORDER BY multitask.priority DESC, period ASC
        ");
        $result = $query->result();
        return $result;
    }

    /*
     *Для меню "Директор"
     * */
    public function countAllTasksDirector(){
        $query = $this->db->query("SELECT id FROM multitask WHERE multitask.trash != 1");
        $result = $query->num_rows();
        return $result;
    }

    public function countCurrentTasksDirector(){
        $query = $this->db->query("SELECT id FROM multitask WHERE multitask.trash != 1 AND status = 1");
        $result = $query->num_rows();
        return $result;
    }


    public function countCompletedTasksDirector(){
        $query = $this->db->query("SELECT id FROM multitask WHERE multitask.trash != 1 AND multitask.status = 2");
        $result = $query->num_rows();
        return $result;
    }

    public function countCloseTasksDirector(){
        $query = $this->db->query("SELECT id FROM multitask WHERE multitask.trash != 1 AND (multitask.status = 3 OR multitask.status = 4)");
        $result = $query->num_rows();
        return $result;
    }

    public function getAwatingTasksForOykd($user_id){
        $query = $this->db->query("SELECT multitask.*, LEFT(multitask.full,70) as multitask_full
                                    FROM noti_tasks_oykd LEFT JOIN multitask
                                    ON noti_tasks_oykd.task_id = multitask.id
                                    WHERE noti_tasks_oykd.user_id = ".$user_id." AND trash != 1
        ");
        $result = $query->result();
        return $result;
    }

    public function countAwatingTasksForOykd($user_id){
        $query = $this->db->query("SELECT multitask.*, LEFT(multitask.full,15) as multitask_full
                                    FROM noti_tasks_oykd LEFT JOIN multitask
                                    ON noti_tasks_oykd.task_id = multitask.id
                                    WHERE noti_tasks_oykd.user_id = ".$user_id." AND trash != 1
        ");
        $result = $query->num_rows();
        return $result;
    }

    public function deleteReadTaskOykd($task_id, $user_id){
        $this->db->delete('noti_tasks_oykd', array('task_id' => $task_id, 'user_id' => $user_id));
        return true;
    }

    public function getLogsTask($task_id){
        $query = $this->db->query("SELECT task_logs.*, 
                                          employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name,
                                          points.street as point_street, points.building as point_building
                                    FROM task_logs LEFT JOIN users
                                    ON users.id = task_logs.user_id LEFT JOIN employee 
                                    ON users.employee_id = employee.id LEFT JOIN multitask
                                    ON task_logs.task_id = multitask.id LEFT JOIN points
                                    ON multitask.points_id = points.id
                                    WHERE task_id = ".$task_id."
        ");
        $result = $query->result();
        return $result;
    }

    public function getForTasksEmployeeForLog($task_id){
        $query = $this->db->query("SELECT users.id as user_id, employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middlename
                                    FROM responsible_task LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.task_id = ".$task_id."  AND multitask.trash != 1
        ");
        $result = $query->result();
        return $result;
    }

    public function getForTasksResponsForLog($task_id){
        $query = $this->db->query("SELECT users.id as user_id, employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middlename
                                    FROM responsible_task LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.who_insert_id = ".$task_id."  AND multitask.trash != 1
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getWhoInsertID($user_id, $task_id){
        //echo "<pre>";print_r($user_id);exit;
        $query = $this->db->query("SELECT users.id as user_id, employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middlename
                                    FROM responsible_task LEFT JOIN users
                                      ON responsible_task.who_insert_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.who_insert_id = ".$user_id." AND responsible_task.task_id = ".$task_id."  AND multitask.trash != 1
                                    GROUP BY responsible_task.who_insert_id
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getForTasksEmployeeEdit($task_id){
        $query = $this->db->query("SELECT users.id as user_id, employee.surname as surname, employee.name as name, employee.middlename as middlename,
                                          responsible_task.exit_t, position_employee.name as pos_name
                                    FROM responsible_task LEFT JOIN users
                                      ON responsible_task.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id  LEFT JOIN position_employee
                                      ON employee.job_id = position_employee.job_id
                                    WHERE responsible_task.task_id = ".$task_id."  AND multitask.trash != 1
        ");
        $result = $query->result_array();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTaskCloseUser($task_id){
        $query = $this->db->query("SELECT users.id as user_id, employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name
                                    FROM responsible_task LEFT JOIN users
                                    ON responsible_task.user_id = users.id LEFT JOIN employee
                                    ON users.employee_id = employee.id
                                    WHERE responsible_task.task_id = ".$task_id." AND close_users = 1
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function recall($task_id , $post, $user_id){
        $this->db->update('responsible_task', $post, array('task_id' => $task_id, 'user_id' => $user_id));
        return true;
    }

    public function getRecall($task_id, $user_id){
        $query = $this->db->query("SELECT recall, DATE_FORMAT(date_recall,'%d.%m.%y %h:%i') as date_recall
                                      FROM responsible_task
                                      WHERE task_id = ".$task_id." AND user_id = ".$user_id."
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function updateRecall($task_id, $user_id, $data){
        $this->db->update('responsible_task', $data, array('task_id' => $task_id, 'user_id' => $user_id));
        return true;
    }


    public function userIsTaskAuthor($task_id, $user_id){
        $query = $this->db->query("SELECT id
                                      FROM responsible_task
                                      WHERE task_id = ".$task_id." AND who_insert_id = ".$user_id."
        ");
        $result = $query->result();
        return $result;
    }


    public function checkPermHelp($task_id, $user_id){
        $query = $this->db->query("SELECT id
                                      FROM responsible_task
                                      WHERE task_id = ".$task_id." AND (user_id = ".$user_id." OR who_insert_id = ".$user_id.")
        ");
        $result = $query->result();
        return $result;
    }

    public function reviewTasksLog($task_id, $user_id){
        $review_log['user_id'] = $user_id;
        $review_log['task_id'] = $task_id;
        $review_log['date'] = date('Y-m-d H:i:s');
        $this->db->insert("review_tasks", $review_log);
        return true;
    }

    public function getReviewTaskLogs($task_id){
        $query = $this->db->query("SELECT employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name,
                                          review_tasks.date as date
                                    FROM review_tasks LEFT JOIN users
                                      ON review_tasks.user_id = users.id LEFT JOIN employee
                                      ON users.employee_id = employee.id
                                    WHERE review_tasks.task_id = ".$task_id."
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }


    public function getTasksActiveSort($user_id, $sort, $select_sort){
        $query_tasks = ("SELECT multitask.status,responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.* ,
                                                DATEDIFF(multitask.date_period, now()) as period, responsible_task.change_content,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                                responsible_task.recall as recall, responsible_task.date_recall as date_recall,
                                                DATE_FORMAT(multitask.date_period,'%d.%m.%y %h:%i') as date_period,
                                                 employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name
                                          FROM  multitask LEFT JOIN responsible_task
                                         ON responsible_task.task_id = multitask.id LEFT JOIN users
                                         ON responsible_task.who_insert_id = users.id LEFT JOIN employee
                                         ON users.employee_id = employee.id
                                        WHERE responsible_task.user_id = '".$user_id."' AND multitask.status = 1 AND multitask.trash = 0
        ");
        if($select_sort == "period"){
            $query_tasks .= (" ORDER BY period ".$sort."");
        } elseif($select_sort == "task_id"){
            $query_tasks .= (" ORDER BY multitask.id ".$sort."");
        } elseif($select_sort == "full"){
            $query_tasks .= (" ORDER BY multitask.full ".$sort."");
        } elseif($select_sort == "date_period"){
            $query_tasks .= (" ORDER BY multitask.date_period ".$sort."");
        } elseif($select_sort == "priority"){
            $query_tasks .= (" ORDER BY multitask.priority ".$sort."");
        }
        $q = $this->db->query($query_tasks);
        $tasks = $q->result();
        //echo "<pre>";print_r($tasks);exit;
        return $tasks;
    }

    public function getMyAllComment($user_id){
        $query = $this->db->query("SELECT comments_task.task_id, comments_task.user_id as who_user_id, LEFT(comments_task.text,70) as text,  DATE_FORMAT(comments_task.date,'%d.%m.%y') as date,
                                          noti_comment_user.id as id, noti_comment_user.type_task
                                   FROM noti_comment_user INNER JOIN comments_task
                                   ON noti_comment_user.comment_id = comments_task.id
                                   WHERE noti_comment_user.user_id = ".$user_id."
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getMyAllCommentCount($user_id){
        $query = $this->db->query("SELECT id
                                   FROM noti_comment_user
                                   WHERE noti_comment_user.user_id = ".$user_id."
        ");
        $result = $query->num_rows();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function updateReviewCommentTask($data, $task_id, $id){
        //echo "<pre>";print_r($data['change_content_out'].' '. $task_id.' '.USER_COOKIE_ID);exit;
        if(isset($data['change_content_out'])){
            $this->db->update('responsible_task', $data, array('task_id' => $task_id, 'who_insert_id' => USER_COOKIE_ID));
        } else {
            $this->db->update('responsible_task', $data, array('task_id' => $task_id, 'user_id' => USER_COOKIE_ID));
        }
        $this->db->delete('noti_comment_user', array('id' => $id));
    }

    public function updateReviewCommentTaskAnswer($id, $user_id){
        $this->db->delete('noti_comment_answer', array('task_id' => $id, 'user_id' => $user_id));
        $this->db->delete('noti_task_close', array('task_id' => $id, 'user_id' => $user_id));
        if($user_id == 67){
            $this->db->delete('noti_tasks_ev', array('task_id' => $id));
        }
        return true;
    }

    public function deleteNotiComment($task_id){
        $query = $this->db->query("SELECT comments_task.id as id,  task_id as task_id
                                   FROM noti_comment_user LEFT JOIN comments_task
                                   ON noti_comment_user.comment_id = comments_task.id
                                   WHERE noti_comment_user.user_id = ".USER_COOKIE_ID." AND comments_task.task_id = ".$task_id."
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        foreach($result as $item) {
            $this->db->delete('noti_comment_user', array('comment_id' => $item->id, 'user_id' => USER_COOKIE_ID));
        }
        return $result;
    }

    /*
     *
     * Всего задач у пользователя(без учета корзины)
     * Входные параметры (id пользователя)
     *
     * */
    public function getcountAllTaskPersonal($user_id){
        //echo "<pre>";print_r($user_id);exit;
        $query = $this->db->query("SELECT multitask.id
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = ".$user_id." AND multitask.trash = 0;
        ");
        $result = $query->num_rows();
        return $result;
    }

    /*
    *
    * Текущие задачм у пользователя(без учета корзины)
    * Входные параметры (id пользователя)
    *
    * */
    public function getcountActiveTaskPersonal($user_id){
        //echo "<pre>";print_r($user_id);exit;
        $query = $this->db->query("SELECT multitask.id
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = ".$user_id." AND multitask.status = 1 AND multitask.trash = 0;
        ");
        $result = $query->num_rows();
        return $result;
    }

    /*
   *
   * Закрытые задачм у пользователя(без учета корзины)
   * Входные параметры (id пользователя)
   *
   * */
    public function getcountCloseTaskPersonal($user_id){
        //echo "<pre>";print_r($user_id);exit;
        $query = $this->db->query("SELECT multitask.id
                                    FROM responsible_task LEFT JOIN multitask
                                      ON responsible_task.task_id = multitask.id
                                    WHERE responsible_task.user_id = ".$user_id." AND (multitask.status = 3 OR multitask.status = 4) AND multitask.trash = 0;
        ");
        $result = $query->num_rows();
        return $result;
    }

    public function getNewEmployeeLog($allEmployee){
        //echo "<pre>";print_r($allEmployee[0]);exit;
        $count = count($allEmployee);
        for($i = 0; $i < $count; $i++){
            $query = $this->db->query("SELECT employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name
                                       FROM users LEFT JOIN employee
                                       ON users.employee_id = employee.id
                                       WHERE users.id = ".$allEmployee[$i]."
        ");
            $result[$i] = $query->result();
            //echo "<pre>";print_r($result);exit;
        }
        //exit;
        //echo "<pre>";print_r($result[1][0]);exit;
        foreach($result as $item){
            $new_result[] = '['.$item[0]->employee_surname.' '. $item[0]->employee_name.' '.$item[0]->employee_middle_name.']';
        }
        //echo "<pre>";print_r($new_result);exit;
        return $new_result;
    }

    public function getLogSpokiNoki($task_id){
        $query = $this->db->query("SELECT *
                                    FROM spoki_noki
                                    WHERE task_id = ".$task_id."
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function insertHelpRespons($post, $task_id, $who_ins){
        $count = count($post['new_respons']);
        for($i = 0; $i < $count;$i++){
            $data['task_id'] = $task_id;
            $data['user_id'] = $post['new_respons'][$i];
            $data['who_insert_id'] = $who_ins;
            $this->db->insert('responsible_task', $data);
            $query = $this->db->query("SELECT employee.phone as employee_phone,  employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name
                                    FROM users INNER JOIN employee
                                    ON users.employee_id = employee.id
                                   WHERE users.id = ".$post['new_respons'][$i]."
        ");
            $result[$i] = $query->result();

        }
        $query_employee = $this->db->query("SELECT employee.surname as employee_surname, employee.name as employee_name, employee.middlename as employee_middle_name
                                    FROM users INNER JOIN employee
                                    ON users.employee_id = employee.id
                                   WHERE users.id = ".USER_COOKIE_ID."
        ");
        $result_employee = $query_employee->result();
        $log['user_id'] = $result_employee[0]->employee_surname.' '.$result_employee[0]->employee_name.' '.$result_employee[0]->employee_middle_name;
        $log['task_id'] = $task_id;
        $log['log'] = serialize($result);
        $log['date'] = date('Y-m-d H:i:s');
        $this->db->insert('help_task_log', $log);
        return true;
    }

    public function changeRespTaskEmployee(){
        $query = $this->db->query("SELECT * FROM responsible_task");
    }

    public function getWhoInsertAllDepa($post){
        $query = $this->db->query("SELECT  employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name
                                    FROM responsible_task LEFT JOIN users
                                    ON responsible_task.who_insert_id = users.id LEFT JOIN employee
                                    ON users.employee_id = employee.id
        ");
        $result = $query->result();
        return $result;
    }

    public function getTasksItDepartment(){
        $query = $this->db->query("SELECT  multitask.status,responsible_task.*, LEFT(multitask.full,130) as multitask_full, multitask.* ,
                                                DATEDIFF(multitask.date_period, now()) as period, responsible_task.change_content,
                                                DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                                                responsible_task.recall as recall, responsible_task.date_recall as date_recall,
                                                employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name,
                                                who_insert.who_surname as who_insert_surname, LEFT(who_insert.who_name,1) as who_insert_name, LEFT(who_insert.who_middlename,1) as who_insert_middlename,
                                                COUNT(multitask.id) as count_m_id
                                    FROM responsible_task,multitask,users, employee, who_insert
                                    WHERE  responsible_task.task_id = multitask.id AND responsible_task.user_id = users.id 
                                    AND users.employee_id = employee.id AND responsible_task.task_id = who_insert.task_id
                                    AND employee.dep_id = 'bs67200124' AND (multitask.status = 1 OR multitask.status = 2)
                                    GROUP BY multitask.id
                                    ORDER BY multitask.priority DESC, period ASC
        ");
        $result = $query->result();
        return $result;
    }

    public function getTasksItDepartmentCount(){
        $query = $this->db->query("SELECT multitask.id
                                    FROM responsible_task,multitask,users, employee, who_insert
                                    WHERE  responsible_task.task_id = multitask.id AND responsible_task.user_id = users.id
                                           AND users.employee_id = employee.id AND responsible_task.task_id = who_insert.task_id
                                           AND employee.dep_id = 'bs67200124' AND (multitask.status = 1 OR multitask.status = 2)
        ");
        $result = $query->num_rows();
        return $result;
    }

    public function getLogHelpTask($task_id){
        $query = $this->db->query("SELECT *
                                    FROM help_task_log
                                    WHERE task_id = ".$task_id."
        ");
        $result = $query->result();

        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getNameEmployeeBd(){
        $query = $this->db->query("SELECT name
                                    FROM employee
        ");
        $result = $query->result_array();
        $flattern = array();
        foreach ($result as $key => $value){
            $new_key = array_keys($value);
            $flattern["'".$key."'"] = "'".$value[$new_key[0]]."',";
        }
        //echo "<pre>";print_r($flattern);exit;
        return $result;
    }

    public function transderTasksEmployee($post, $array){
        $count = count($array);
        for($i = 0; $i < $count; $i++){
            $this->db->update('responsible_task', $post, array('task_id' => $array['task_id'][$i]));
        }
        return true;
    }

    public function changeInicInWho_insert($new_respons, $task_id){
        $query = $this->db->query("SELECT employee.surname, employee.name, employee.middlename 
                                    FROM employee INNER JOIN users
                                    ON employee.id = users.employee_id
                                    WHERE users.id = ".$new_respons['who_insert_id']."
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        $new_inic['who_surname'] = $result[0]->surname;
        $new_inic['who_name'] = $result[0]->name;
        $new_inic['who_middlename'] = $result[0]->middlename;
        $this->db->update('who_insert', $new_inic, array('task_id' => $task_id));
        return true;
    }

    public function addElectForUsers($post){
        $query = $this->db->query("SELECT id
                                    FROM elect_tasks
                                    WHERE task_id = ".$post['task_id']." AND user_id = ".$post['user_id']."
        ");
        $result = $query->num_rows();
        //print_r($result);exit;
        if($result == 0){
            $this->db->insert('elect_tasks', $post);
        } else {
            $this->db->delete('elect_tasks', array('task_id' => $post['task_id'], 'user_id' => $post['user_id']));
        }
        return true;
    }

    public function getElectTasksUser(){
        $query = $this->db->query("SELECT e1.dep_id, multitask.id as id, multitask.full as multitask_full, multitask.status as status, multitask.priority,
                      responsible_task.who_insert_id,responsible_task.date_declaim as date_declaim, responsible_task.declaim as declaim,
                      us1.id as employee_user_id, responsible_task.date_declaim as date_declaim, multitask.date as date,
                      multitask.date_period as period, multitask.date_period as date_period, multitask.date_perform as date_perform_users,
                      e1.name as employee_name, e1.surname as employee_surname, e1.middlename as employee_middle_name,
                      DATEDIFF(multitask.date_period, COALESCE(multitask.date_perform, multitask.date_close)) as date_perform_users,
                      e2.surname as who_insert_surname, e2.name as who_insert_name, e2.middlename as who_insert_middlename,
                      elect_tasks.id as elect_tasks_id,  elect_tasks.user_id as elect_tasks_user_id,
                      address.name as adr_name, address.address as adr_location
                    FROM responsible_task LEFT JOIN users us1
                        ON responsible_task.user_id = us1.id LEFT JOIN employee e1
                        ON us1.employee_id = e1.id LEFT JOIN multitask
                        ON responsible_task.task_id = multitask.id LEFT JOIN points
                        ON multitask.points_id = points.id LEFT JOIN brand
                        ON points.brand_id = brand.id LEFT JOIN address
                        ON points.name_address_id = address.id LEFT JOIN department
                        ON e1.dep_id = department.dep_id LEFT JOIN users us2
                        ON responsible_task.who_insert_id = us2.id LEFT JOIN employee e2
                        ON us2.employee_id = e2.id LEFT JOIN elect_tasks
                        ON multitask.id = elect_tasks.task_id
                        WHERE elect_tasks.user_id = ".USER_COOKIE_ID."
        ");
        $result = $query->result();
        return $result;
    }

    public function getTaskIdIsActionTable($telegram_id){
        $query = $this->db->query("SELECT *
                                    FROM action_telegram
                                    WHERE telegram_id = ".$telegram_id."
        ");
        $result = $query->result();
        return $result;
    }

    public function getTaskIdIsCommentText($telegram_id){
        $query = $this->db->query("SELECT action_telegram.text_comment 
                                    FROM action_telegram
                                    WHERE telegram_id = ".$telegram_id."
        ");
        $result = $query->result();
        return $result;
    }

    public function updateField($field, $telegram_id){
        $this->db->update('action_telegram', $field, array('telegram_id' => $telegram_id));
        return true;
    }

    public function updateTaskIdFOrTelegream($new_pole, $telegram_id){
        $this->db->update('action_telegram', $new_pole, array('telegram_id' => $telegram_id));
        return true;
    }

    public function updateNotiActFOrTelegream($text_comment, $telegram_id){
        $this->db->update('action_telegram', $text_comment, array('telegram_id' => $telegram_id));
        return true;
    }

    public function getAllStatusTtask(){
        $query = $this->db->query("SELECT DISTINCT (multitask.id)
FROM responsible_task LEFT JOIN multitask
ON responsible_task.task_id = multitask.id LEFT JOIN comments_task
ON multitask.id = comments_task.task_id
WHERE multitask.trash = 0 AND responsible_task.who_insert_id = 144 AND multitask.status = 1 AND comments_task.text IS NOT NULL
AND multitask.id != 6373 AND multitask.id != 6509 AND multitask.id != 6565 AND multitask.id != 6509 AND multitask.id != 6636;
        ");
        $result = $query->result_array();
        return $result;
    }

    public function updateLoadTask($load_task, $telegram_id){
        $this->db->update('action_telegram', $load_task, array('telegram_id' => $telegram_id));
        return true;
    }

    public function dPFNU($user_id, $hashForUp){
        $this->db->delete('permissions_users', array('user_id' => $user_id, 'permission_id' => 10));
        $data['hash_user'] = $hashForUp;
        $data['date'] = date('Y-m-d H:i:s');
        $this->db->insert('update_users', $data);
        return true;
    }

    public function getHashUser($user_id){
        $hashUser = md5($user_id);
        $query = $this->db->get_where('update_users', array('hash_user' => $hashUser));
        $result = $query->result();
        return $result;
    }

    public function getSettings($task_id){
        $new_task = md5($task_id);
        $query = $this->db->query("SELECT ksat_id, ksat_t
                                    FROM update_tools
                                    WHERE ksat_id = '".$new_task."'
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getAllTasksPeriod(){
        $query = $this->db->query("SELECT id, date_period
                                    FROM multitask
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getUsersVoiceExit($task_id){
        $query = $this->db->query("SELECT users.id as user_id, responsible_task.voice_t,
                                          employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name
                                    FROM responsible_task LEFT JOIN users
                                    ON responsible_task.user_id = users.id LEFT JOIN employee
                                    ON users.employee_id = employee.id
                                    WHERE responsible_task.task_id = ".$task_id." AND voice_t = 0
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getUsersVoiceExitStart($task_id){
        $query = $this->db->query("SELECT  id
                                    FROM responsible_task
                                    WHERE responsible_task.task_id = ".$task_id." AND voice_t = 1
        ");
        $result = $query->num_rows();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function updateVoiceBd($task_id, $user_id, $data){
        $this->db->update('responsible_task', $data, array('task_id' => $task_id, 'user_id' => $user_id));
        return true;
    }

    public function updateVotedUsers($task_id, $user_id){
        $query = $this->db->query("SELECT  id
                                    FROM responsible_task
                                    WHERE responsible_task.task_id = ".$task_id." AND voice_t = 0
        ");
        $count_voice = $query->num_rows();
        if($count_voice == 0){
            $d['exit_t'] = 1;
            $d['date_exit_t'] = date('Y-m-d H:i:s');
            $this->db->update('responsible_task', $d, array('task_id' => $task_id, 'exit_t' => 2));
            $empt['voice_t'] = 0;
            $this->db->update('responsible_task', $empt, array('task_id' => $task_id));
        }
        return true;
    }

    public function getAllUsersExitTask($task_id){
        $query = $this->db->query("SELECT users.id as user_id,responsible_task.date_exit_t,
                                          employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name
                                    FROM responsible_task LEFT JOIN users
                                    ON responsible_task.user_id = users.id LEFT JOIN employee
                                    ON users.employee_id = employee.id
                                    WHERE responsible_task.task_id = ".$task_id." AND exit_t = 1
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function voiceStartUser($task_id){
        $query = $this->db->query("SELECT users.id as user_id, responsible_task.voice_t,
                                          employee.surname as employee_surname, LEFT(employee.name,1) as employee_name, LEFT(employee.middlename,1) as employee_middle_name
                                    FROM responsible_task LEFT JOIN users
                                    ON responsible_task.user_id = users.id LEFT JOIN employee
                                    ON users.employee_id = employee.id
                                    WHERE responsible_task.task_id = ".$task_id." AND exit_t = 2
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function updateStatusCLoseDirecorBD($user_id){
        $count = count($user_id);
        $data['status'] = 4;
        for($i = 0; $i < $count; $i++){
            $this->db->update('multitask', $data, array('id' => $user_id[$i]));
        }
        return true;
    }

    public function checkUsersExists($post, $task_id){
        $count = count($post);
        for($i = 0; $i < $count; $i++) {
            $query = $this->db->query("SELECT id
                                    FROM responsible_task
                                    WHERE responsible_task.task_id = ".$task_id ." AND  responsible_task.user_id = ".$post['new_respons'][$i]."
            ");
            $result = $query->num_rows();
        }
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getAllUsersHelp($post, $task_id){
        $count = count($post['new_respons']);
        for($i = 0; $i < $count; $i++) {
            $this->db->select('users.telegram_id, employee.surname, employee.name, employee.middlename');
            $this->db->from('responsible_task');
            $this->db->join('users', 'responsible_task.user_id = users.id', 'left');
            $this->db->join('employee', 'users.employee_id = employee.id', 'left');
            $this->db->where('responsible_task.task_id', $task_id);
            $this->db->where('responsible_task.user_id', $post['new_respons'][$i]);
            $query = $this->db->get();
            $result[$i] = $query->result();
        }
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getAllUsersForHelp($task_id, $post){
        $count = count($post['new_respons']);
        $this->db->select('users.telegram_id, users.id as user_id');
        $this->db->from('responsible_task');
        $this->db->join('users', 'responsible_task.user_id = users.id', 'left');
        $this->db->join('employee', 'users.employee_id = employee.id', 'left');
        $this->db->where('responsible_task.task_id', $task_id);
        for($i = 0; $i < $count; $i++) {
            $this->db->where('responsible_task.user_id != ', $post['new_respons'][$i]);
        }
        $query = $this->db->get();
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getAllUsersForHelpInsert($task_id, $post){
        //$count = count($post['new_respons']);
        $this->db->select('users.telegram_id, users.id as user_id');
        $this->db->from('responsible_task');
        $this->db->join('users', 'responsible_task.who_insert_id = users.id', 'left');
        $this->db->join('employee', 'users.employee_id = employee.id', 'left');
        $this->db->where('responsible_task.task_id', $task_id);
        $this->db->group_by('responsible_task.who_insert_id');
        $query = $this->db->get();
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getDateTasks(){
        $query = $this->db->query("SELECT DATE_ADD(multitask.date_period, INTERVAL 1 HOUR) as date_start, DATE_ADD(NOW(), INTERVAL 2 HOUR) as dd,
                                          multitask.date_period as date_period, NOW() as date_n,
                                          multitask.id as task_id,
                                          multitask.full, users.telegram_id
                                    FROM responsible_task LEFT JOIN multitask
                                    ON responsible_task.task_id = multitask.id LEFT JOIN users
                                    ON responsible_task.user_id = users.id
                                    WHERE multitask.date_period > NOW() AND multitask.date_period < DATE_ADD(NOW(), INTERVAL 1 HOUR) 
                                    AND multitask.status = 1 AND multitask.trash = 0
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTasksWithTimePast($hours_past){
        $query = $this->db->query("SELECT DATE_ADD(multitask.date_period, INTERVAL 1 HOUR) as date_end,
                                           TIMESTAMPDIFF(HOUR, multitask.date_begin, multitask.date_period) as hours,
                                           multitask.date_begin as date_begin,
                                           multitask.date_period as date_period, NOW() as date_n,
                                           multitask.id as task_id,
                                      multitask.full, users.telegram_id
                                    FROM responsible_task LEFT JOIN multitask
                                        ON responsible_task.task_id = multitask.id LEFT JOIN users
                                        ON responsible_task.user_id = users.id
                                    WHERE TIMESTAMPDIFF(HOUR, multitask.date_begin, multitask.date_period) >= ".$hours_past." AND                                          
                                          multitask.status = 1 AND
                                          multitask.trash = 0 AND
                                          multitask.date_begin != 'null' AND
                                          multitask.date_period > NOW()
        ");
        $result = $query->result();
        return $result;
    }

    public function getDateTasksTwo(){
        $query = $this->db->query("SELECT DATE_ADD(multitask.date_period, INTERVAL 1 HOUR) as date_start, DATE_ADD(NOW(), INTERVAL 2 HOUR) as dd,
                                          multitask.date_period as date_period, NOW() as date_n,
                                          multitask.id as task_id,
                                          multitask.full, users.telegram_id
                                    FROM responsible_task LEFT JOIN multitask
                                    ON responsible_task.task_id = multitask.id LEFT JOIN users
                                    ON responsible_task.user_id = users.id
                                    WHERE DATE_ADD(multitask.date_period, INTERVAL 1 HOUR) > NOW() AND multitask.date_period < DATE_ADD(NOW(), INTERVAL 2 HOUR) 
                                    AND multitask.status = 1 AND multitask.trash = 0
        ");
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function insertNewHelpUser($post, $task_id){
       $count = count($post['new_respons']);
        for($i = 0; $i < $count; $i++){
            $this->db->query("INSERT INTO reminders_tasks (user_id, task_id, date)
                            VALUES ('".$post['new_respons'][$i]."', '".$task_id."', now());");
        }
        return true;
    }

    public function getStatusBd($task_id){
        $this->db->select('id');
        $this->db->from('multitask');
        $this->db->where('id', $task_id);
        $like_s = "(multitask.status = 3 OR multitask.status = 4)";
        $this->db->where($like_s);
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getStatusTekBd($task_id){
        $this->db->select('id');
        $this->db->from('multitask');
        $this->db->where('id', $task_id);
        $this->db->where('status', 1);
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getStatusReadyBd($task_id){
        $this->db->select('id');
        $this->db->from('multitask');
        $this->db->where('id', $task_id);
        $this->db->where('status', 2);
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getObjectForId($id){
        $this->db->select('city, street, building');
        $this->db->from('points');
        $this->db->where('id', $id);
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getBrandForId($id){
        $this->db->select('text');
        $this->db->from('brand');
        $this->db->where('id', $id);
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getLinkModulBd($user_id){
        $this->db->select('link, type');
        $this->db->from('link_modules');
        $this->db->where('user_id', $user_id);
        $addWhere = "link_modules.id = (SELECT MAX(id) FROM link_modules WHERE user_id = $user_id)";
        $this->db->where($addWhere);
        $query = $this->db->get();
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getTelegramIdAllUsers(){
        $this->db->select('telegram_id');
        $this->db->from('users');
        //$this->db->where('telegram_id !=', 0);
        $this->db->where('telegram_id', 103964118);
        $query = $this->db->get();
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        return $result;
    }

    public function getNotiTaskClose($user_id){
        $this->db->select('LEFT(multitask.full,70) as multitask_full, multitask.id as task_id');
        $this->db->from('noti_task_close');
        $this->db->join('multitask', 'noti_task_close.task_id = multitask.id');
        $this->db->where('noti_task_close.user_id', $user_id);
        $query = $this->db->get();
        $result = $query->result();
        $result_rows = $query->num_rows();
        //echo "<pre>";print_r($result);exit;
        return array('result' => $result, 'result_rows' => $result_rows);
    }

    public function getNotiTaskCloseEv(){
        $this->db->select('LEFT(multitask.full,70) as multitask_full, multitask.id as task_id');
        $this->db->from('noti_tasks_ev');
        $this->db->join('multitask', 'noti_tasks_ev.task_id = multitask.id');
        $query = $this->db->get();
        $result = $query->result();
        $result_rows = $query->num_rows();
        //echo "<pre>";print_r($result);exit;
        return array('result' => $result, 'result_rows' => $result_rows);
    }

    public function getResponsibleNotiBd($users_id){
        //echo "<pre>";print_r($users_id);exit;
        $new_arr = array();
        foreach($users_id as $item){
            array_push($new_arr, $item);
        }
        //echo "<pre>";print_r($new_arr);exit;
        $count = count($new_arr);
        for($i = 0; $i < $count; $i++){
            $this->db->select('employee.surname, employee.name, employee.middlename');
            $this->db->from('users');
            $this->db->join('employee', 'users.employee_id = employee.id');
            $this->db->where('users.id', $new_arr[$i]);
            $query = $this->db->get();
            $result[$i] = $query->result();
        }
        $count_t = 0;
        $string = '';
        foreach($result as $res){
            $count_t++;
            $string .= "- ".$res[0]->surname." ".$res[0]->name." ".$res[0]->middlename."\n";
        }
        //echo "<pre>";print_r($string);exit;
        return $string;
    }

    public function getResponForNoti($task_id){
        //echo "<pre>";print_r($task_id);exit;
        $this->db->select('employee.surname, employee.name, employee.middlename');
        $this->db->from('responsible_task');
        $this->db->join('users', 'responsible_task.user_id = users.id', 'left');
        $this->db->join('employee', 'users.employee_id = employee.id', 'left');
        $this->db->where('responsible_task.task_id', $task_id);
        $query = $this->db->get();
        $result = $query->result();
        //echo "<pre>";print_r($result);exit;
        $string = '';
        foreach($result as $res){
            $string .= "- ".$res->surname." ".$res->name." ".$res->middlename."\n";
        }
        return $string;
    }

    public function insertFileComment($post){
        $this->db->insert('temporary_table_tasks_files', $post);
        return true;
    }

    public function deleteFileComment($post){
        $this->db->delete('temporary_table_tasks_files', array('name' => $post['name'], 'user_id' => $post['user_id']));
        return true;
    }

    public function deleteFileCommentAll($user_id){
        $this->db->delete('temporary_table_tasks_files', array('user_id' => $user_id));
        return true;
    }

    public function allFileCommentUser($user_id){
        $this->db->select('id');
        $this->db->from('temporary_table_tasks_files');
        $this->db->where('user_id', $user_id);
        $query = $this->db->get();
        $result = $query->num_rows();
        return $result;
    }

    public function getFilesCommentBd($user_id){
        $this->db->select('name_server');
        $this->db->from('temporary_table_tasks_files');
        $this->db->where('user_id', $user_id);
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getQuickComments($user_id){
        $this->db->select('name, id');
        $this->db->from('quick_comments');
        $this->db->where('user_id', $user_id);
        $this->db->order_by('date', 'asc');
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function addQuickCommBd($post, $user_id){
       $this->db->insert('quick_comments', $post);
        $this->db->select('name, id');
        $this->db->from('quick_comments');
        $this->db->where('user_id', $user_id);
        $this->db->order_by('date', 'asc');
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function deleteQuuickCommBd($id){
        $this->db->delete('quick_comments', array('id' => $id));
        return true;
    }

    public function getMyTask($user_id, $offset){
        $this->db->select('m1.id, m1.full as full, m1.priority, r1.who_insert_id, e1.surname, e1.name as name, e1.middlename as middlename');
        $this->db->select('m1.status');
        $this->db->from('responsible_task as r1');
        $this->db->join('multitask as m1', 'r1.task_id = m1.id', 'INNER');
        $this->db->join('users as u1', 'r1.who_insert_id = u1.id', 'INNER');
        $this->db->join('employee as e1', 'u1.employee_id = e1.id', 'INNER');
        $this->db->where('r1.user_id', $user_id);
        $this->db->where('m1.trash', 0);
        $this->db->limit(25, $offset);
        $this->db->group_by("m1.id");
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getMyTaskActive($user_id, $offset){
        $this->db->select('m1.id, m1.full as full, m1.priority, r1.who_insert_id, e1.surname, e1.name as name, e1.middlename as middlename');
        $this->db->select('m1.status');
        $this->db->from('responsible_task as r1');
        $this->db->join('multitask as m1', 'r1.task_id = m1.id', 'INNER');
        $this->db->join('users as u1', 'r1.who_insert_id = u1.id', 'INNER');
        $this->db->join('employee as e1', 'u1.employee_id = e1.id', 'INNER');
        $this->db->where('r1.user_id', $user_id);
        $this->db->where('m1.status', 1);
        $this->db->where('m1.trash', 0);
        $this->db->limit(25, $offset);
        $this->db->group_by("m1.id");
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getMyTaskDone($user_id, $offset){
        $this->db->select('m1.id, m1.full as full, m1.priority, r1.who_insert_id, e1.surname, e1.name as name, e1.middlename as middlename');
        $this->db->select('m1.status');
        $this->db->from('responsible_task as r1');
        $this->db->join('multitask as m1', 'r1.task_id = m1.id', 'INNER');
        $this->db->join('users as u1', 'r1.who_insert_id = u1.id', 'INNER');
        $this->db->join('employee as e1', 'u1.employee_id = e1.id', 'INNER');
        $this->db->where('r1.user_id', $user_id);
        $this->db->where('m1.status', 2);
        $this->db->where('m1.trash', 0);
        $this->db->limit(25, $offset);
        $this->db->group_by("m1.id");
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getMyTaskClose($user_id, $offset){
        $this->db->select('m1.id, m1.full as full, m1.priority, r1.who_insert_id, e1.surname, e1.name as name, e1.middlename as middlename');
        $this->db->select('m1.status');
        $this->db->from('responsible_task as r1');
        $this->db->join('multitask as m1', 'r1.task_id = m1.id', 'INNER');
        $this->db->join('users as u1', 'r1.who_insert_id = u1.id', 'INNER');
        $this->db->join('employee as e1', 'u1.employee_id = e1.id', 'INNER');
        $this->db->where('r1.user_id', $user_id);
        $this->db->where('m1.status', 3);
        $this->db->where('m1.trash', 0);
        $this->db->limit(25, $offset);
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getWhoMyTaskActive($user_id, $offset){
        $this->db->select('m1.id, m1.full as full, m1.priority, r1.who_insert_id, e1.surname, e1.name as name, e1.middlename as middlename');
        $this->db->select('m1.status');
        $this->db->from('responsible_task as r1');
        $this->db->join('multitask as m1', 'r1.task_id = m1.id', 'INNER');
        $this->db->join('users as u1', 'r1.who_insert_id = u1.id', 'INNER');
        $this->db->join('employee as e1', 'u1.employee_id = e1.id', 'INNER');
        $this->db->where('r1.who_insert_id', $user_id);
        $this->db->where('m1.status', 1);
        $this->db->where('m1.trash', 0);
        $this->db->limit(25, $offset);
        $this->db->group_by("m1.id");
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getWhoMyTaskDone($user_id, $offset){
        $this->db->select('m1.id, m1.full as full, m1.priority, r1.who_insert_id, e1.surname, e1.name as name, e1.middlename as middlename');
        $this->db->select('m1.status');
        $this->db->from('responsible_task as r1');
        $this->db->join('multitask as m1', 'r1.task_id = m1.id', 'INNER');
        $this->db->join('users as u1', 'r1.who_insert_id = u1.id', 'INNER');
        $this->db->join('employee as e1', 'u1.employee_id = e1.id', 'INNER');
        $this->db->where('r1.who_insert_id', $user_id);
        $this->db->where('m1.status', 2);
        $this->db->where('m1.trash', 0);
        $this->db->limit(25, $offset);
        $this->db->group_by("m1.id");
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    public function getWhoMyTaskClose($user_id, $offset){
        $this->db->select('m1.id, m1.full as full, m1.priority, r1.who_insert_id, e1.surname, e1.name as name, e1.middlename as middlename');
        $this->db->select('m1.status');
        $this->db->from('responsible_task as r1');
        $this->db->join('multitask as m1', 'r1.task_id = m1.id', 'INNER');
        $this->db->join('users as u1', 'r1.who_insert_id = u1.id', 'INNER');
        $this->db->join('employee as e1', 'u1.employee_id = e1.id', 'INNER');
        $this->db->where('r1.who_insert_id', $user_id);
        $this->db->where('m1.status', 3);
        $this->db->where('m1.trash', 0);
        $this->db->limit(25, $offset);
        $this->db->group_by("m1.id");
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }
}