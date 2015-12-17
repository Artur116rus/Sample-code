<?php

/**
* Лицевые счета
*/
class AccountController extends Zend_Controller_Action {

    function init() {
        $this->_helper->Init->init();
        if(!$this->user->isLogged()) {
           Functions::redirect('//'.Settings::get('root_domain').'/login/');
        }
    }

    function viewAction() {
        $code = strtolower($this->_getParam('type'));
        if(!in_array($code, array('ro', 'spec'))) {
        	Functions::redirect('/');
		}
		$code2 = ucfirst($code);
		$class_name = "Type_Billing_Bill_{$code2}_Account";
        $account_id = $this->_getParam('id');
        $account = new $class_name($account_id);
        $bill_field = "billing_bill_{$code}_id";
        $bill_id = $account->$bill_field;
        $form_code = $account->is_legal_person ? 'org' : 'personal';
        $url = "/{$code}account/{$bill_id}/{$form_code}account/edit/{$account_id}";
        Functions::redirect($url);
    }

	/**
	 * Открытие лицевого счета
	 * @ajax
	 */
	public function openaccountAction() {
		$form = (object)$this->_getParam('form');		
		try {
			$resp = Service::Billing_Account()->openAccount($this->user->getSessionId(), $form->number);
            echo json_encode(array('status' => 'success', 'message' => 'Счет успешно открыт.'));
		} catch (\Exception $ex) {
			echo json_encode(array('status' => 'error', 'message' => $ex->getMessage()));
		}
	}

	/**
	 * Импорт собсвенников из CSV
	 * @ajax
	 */
	public function importownerAction() {
		$form = (object)$_POST['form'];
        // проверка на существование файла
        if (  (!isset($_FILES['form']['name']['file'])) || ( !$_FILES['form']['tmp_name']['file'] )  ) {
            throw new Exception('Не выбран файл', 950);
        }
		$set_debt = (isset($_POST['form']['set_debt']) && $_POST['form']['set_debt']) ? true : false;
        $ext = explode('.', $_FILES['form']['name']['file']);
        $ext = strtolower(end($ext));
        // проверка расширения файла
        if(!in_array($ext, array('csv'))) {
            throw new Exception('Недопустимый формат файла', 950);
        }
        // временный файл
        $file = $_FILES['form']['tmp_name']['file'];

		$dir = dirname(__FILE__)."/../../../public/";
		$errorFile = "/temp/owner_error_file_".date("Ymd_H_i_s").".csv";
		$error = FALSE;
        $data = array();

        $parser = new \Etton\Service\Billing\Parser\Account\Owner\CsvParser($file);
        try {
            $data = $parser->parse();
        } catch (\Exception $e) {
            file_put_contents($dir.$errorFile, $e->getMessage() . "\n", FILE_APPEND);
        }

		foreach ($data as $index => $row) {
			if($index == 0) {
				$row[] = 'error_column';
				file_put_contents($dir.$errorFile, implode($row, ';')."\n", FILE_APPEND);
				continue;
			}
			try {
                if (count($row) != 17) {
                    continue;
                }
                // создание объекта дом
                $home = array(
                    'district_prefix' => $row[0],
                    'district_title'  => $row[1],
                    'locality_prefix' => $row[2],
                    'locality_title'  => $row[3],
                    'street_prefix'   => $row[4],
                    'street_title'    => $row[5],
                    'house_number'    => $row[6],
                    'house_block'     => $row[7],
                );
                // создание объекта помещение
                $apartment = array(
                    'flat'            => $row[8],
                    'total_flat_area' => $row[9],
                    'date_open'       => $row[10],
                    'cadastr_number'  => $row[11],
                    'credit_dt_start' => $row[12],
                );
                // создание объекта собственник
                $owner  = array(
                    'fio'                   => $row[13],
                    'owner_area_proportion' => $row[14],
                    'date_registration'     => $row[15],
                    'reg_no'                => $row[16],
                );

                $params = array(
                    'apartment_check' => property_exists($form, 'apartment_check') ? true : false,
					'set_debt' => $set_debt,
                );

                $resp = Service::Admin_Import()->importOwner($home, $apartment, $owner, $params);
                if (!$resp) {
                    throw new Exception('Произошла ошибка при импорте собсвенника');
                }
            } catch (Exception $ex) {
                $error = true;
                $row[] = $ex->getMessage();
                file_put_contents($dir . $errorFile, implode($row, ';') . "\n", FILE_APPEND);
            }
		}
        if ($error) {
            echo json_encode(array('status' => 'success', 'message' => null, 'redirect' => $errorFile));
        } else {
            echo json_encode(array('status' => 'success', 'message' => 'Собственники загружены успешно'));
        }
	}

    /**
     * @ajax
     */
    public function cancelchangeownerAction()
    {
        try {
            if (!$this->_request->isPost()) {
                throw new \RuntimeException("Неверный запрос");
            }
            $accountId = $this->_request->getPost('id');
            if (empty($accountId)) {
                throw new \RuntimeException("Не указан лицевой счет");
            }

            $service = new \Etton\Service\Billing\Account\Account();
            $account = $service->getById($accountId);
            if (empty($account)) {
                throw new \RuntimeException(sprintf("Аккаунт с ид %d не найден", $accountId));
            }
            $type = !empty($account['billing_bill_ro_id']) ? 'ro' : 'spec';

            Model_Billing_Bill_Account_Operation::checkChangeOwnerCanBeCanceled($accountId, $type);

            $newAccountId = $service->cancelChangeOwner($accountId, $type, $this->user->getId());
            $link = sprintf("/account/%s/%d/", $type, $newAccountId);

            echo json_encode(array('status' => 'success', 'message' => null, 'link' => $link));
        } catch (\Exception $e) {
            echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
        }
    }
}