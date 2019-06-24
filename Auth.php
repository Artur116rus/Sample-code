<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Entity\User;

class Auth extends MY_Controller
{
    const TELEGRAM_REDIRECT_URL = 'https://telegram.me/BuketStolic_bot';

    static $need_auth = false;

    public function __construct(){
        parent::__construct();
        $this->load->model('auth_model');
        $this->load->library('users_action_library');
    }

    public function index($telegramId = null)
    {
        $this->load->library('auth_library');
        $this->load->library('session');

        if (isset($_COOKIE['id']) && isset($_COOKIE['hash'])) {
            redirect('/main');
        } else {
            $this->showAuthFrom($telegramId);
        }
    }

    private function showAuthFrom($telegramId = null)
    {
        $this->load->library('auth_library');

        $data = new stdClass();
        $data->errors = [];

        if ($error = $this->session->flashdata('error')) {
            $data->errors[] = $error;
        }

        $data->authWay = $this->session->flashdata('authWay');
        if (!$data->authWay)
            $data->authWay = 'phone';

        $data->identificator = $this->session->flashdata('identificator');

        $data->inversedAuthWay = Auth_library::inverseAuthWay($data->authWay);
        $data->inversedAuthWayMessage = Auth_library::getAuthWayMessage($data->inversedAuthWay);

        $data->telegramId = $telegramId;

        $this->load->view('/auth/auth_view', $data);
    }

    private function getCurlData($url){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.16) Gecko/20110319 Firefox/3.6.16");
        $curlData = curl_exec($curl);
        curl_close($curl);
        return $curlData;
    }

    public function authorize()
    {
        $authWay = $this->input->post('authWay');
        $identificator = $this->input->post('identificator');
        $password = $this->input->post('password');
        $telegramId = $this->input->post('telegramId');

        try {
            $authResult = User::authorize($authWay, $identificator, $password, $telegramId);
            if ($authResult->success) {
                if ($telegramId) {
                    redirect(self::TELEGRAM_REDIRECT_URL);
                } else {
                    redirect('/main');
                }
            } else {
                var_dump($authResult);
            }
        } catch (AuthException $exception) {
            // подключаем библеотеку работы с сессиями
            $this->load->library('session');
            // Записываем в одноразовую сессию сообщение об ошибке
            $this->session->set_flashdata('error', $exception->getMessage());
            // Записываем в одноразовую сессию способ авторизации
            $this->session->set_flashdata('authWay', $authWay);
            // Записываем в одноразовую сессию логин или пароль
            $this->session->set_flashdata('identificator', $identificator);

            if ($telegramId) {
                redirect(PROTOCOL_CONST . "://" . SERVER_NAME_CONST . "/auth/authTelegram?id={$telegramId}");
            } else {
                redirect(PROTOCOL_CONST . "://" . SERVER_NAME_CONST . "/auth");
            }
        }
    }

    public function logout($user_id = null){
        if (!is_numeric($user_id)) {
            show_404();
        }
        $this->auth_model->deleteUsersOnline($user_id);
        $this->users_action_library->add(OUTPUT_LOG_7, $user_id);
        delete_cookie('id');
        delete_cookie('hash');
        //$this->session->sess_destroy();
        redirect('/auth');
    }

    //Принудительное уничтожение сессии у всех пользователей. Использутеся в крайнем случае. Вызывает ajax(который находится в footer)
    public function deleteSession(){
        $this->session->sess_destroy();
        echo json_encode(array('status' => 'success'));
    }

    public function getParamettr(){
        //echo "<pre>";print_r($_GET);exit;
        $post['login'] = htmlspecialchars(strip_tags(trim($_GET['login'])));
        $post['password'] = md5(htmlspecialchars(strip_tags(trim($_GET['password']))));
        $result = $this->auth_model->checkUser($post);
        if (!empty($result)) {
            $data['id'] = $result[0]->id;
            $data['hash'] = $result[0]->hash;
            echo json_encode(array('status' => 'success', 'data' => $data));
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    public function no_js(){
        $this->load->view('no_js');
    }

    public function authTelegram()
    {
        $CI =& self::get_instance();
        $CI->load->model('users_model');

        $telegramId = (int)$_GET['id'];
        if (!isset($telegramId) || empty($telegramId)) {
            show_404();
        } else {
            $users = Users_model::get('id', ['telegram_id' => $telegramId]);
            if (empty($users)) {
                $this->showAuthFrom($telegramId);
            } else {
                redirect(self::TELEGRAM_REDIRECT_URL);
            }
        }
    }

    public function logOutTelegram()
    {
        $CI =& self::get_instance();
        $CI->load->model('users_model');

        $telegramId = (int)$_GET['id'];
        //print_r($getTelegramId);
        if (!isset($telegramId) && empty($telegramId)) {
            show_404();
        } else {
            $users = Users_model::get('id', ['telegram_id' => $telegramId]);
            if (!empty($users)) {
                $updateTelegramIdResult = Users_model::update(['telegram_id' => 0], ['id' => $users[0]->id]);
                if ($updateTelegramIdResult) {
                    echo json_encode(array('status' => 'success'));
                } else {
                    echo json_encode(array('status' => 'error'));
                }
            } else {
                show_404();
            }
        }
    }
}
