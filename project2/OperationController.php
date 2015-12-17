<?php

/**
* Лицевые счета
*/
class OperationController extends Zend_Controller_Action {

    function init() {
        $this->_helper->Init->init();
        if(!$this->user->isLogged()) {
           Functions::redirect('//'.Settings::get('root_domain').'/login/');
        }
    }
	/**
	 * @ajax
	 */
    function addAction() {
		$form = (object)$_POST['form'];
		if($form->billing_bill_operation_type_id == Constant::BILLING_BILL_OPERATION_TYPE_DEBIT_KR) {
			$form = (array)$form;
			$form['debit'] = $form['debit_kr'];
			$form = (object)$form;
		}
		$form->date = date('Y-m-d', strtotime($form->date));
        try {
			if(in_array(Constant::VAR_REGION_CODE, array('udmurt', 'yanao', 'lipetsk', 'magadan')) && $form->billing_bill_operation_type_id == Constant::BILLING_BILL_OPERATION_TYPE_ERROR_PAID) {
				Service::Billing()->returnPaidOperation($this->user->getSessionId(), $form);
			} elseif ($form->billing_bill_operation_type_id == Constant::BILLING_BILL_OPERATION_TYPE_PERCENT) {
                $amount = $form->percent_sum;
                $form->debit = $amount;
                unset($form->percent_sum);

                $form = Functions::cast($form, 'Type_Billing_Bill_Operation');

                $new_id = Service::Billing()->addBillOperation($this->user->getSessionId(), $form);
                if (!$new_id) {
                    throw new \RuntimeException("Ошибка во время создании операции");
                }
            } else {
				if(!$form->billing_bill_ro_id && !$form->billing_bill_spec_id) {
					throw new Exception('Не указан счет');
				}
				if(!$form->date) {
					throw new Exception('Выберите дату проводки');
				}
				if(!$form->home_id) {
					throw new Exception('Не указан идентификатор дома');
				}
				if(!$form->billing_bill_operation_type_id) {
					throw new Exception('Выберите тип операции');
				}

				if(in_array(Constant::VAR_REGION_CODE, array('orel')) && $form->billing_bill_operation_type_id == 2) {
					if(!$form->contractor_id) {
						throw new Exception('Выберите подрядную организацию');
					}
				}
				if(
					isset($form->billing_bill_payment_type_id) &&
					!$form->billing_bill_payment_type_id &&
					!in_array(
						$form->billing_bill_operation_type_id,
						array(
							Constant::BILLING_BILL_OPERATION_TYPE_RETURN_CONTRACTOR,
							Constant::BILLING_BILL_OPERATION_TYPE_DEBIT_KR,
							Constant::BILLING_BILL_OPERATION_TYPE_PAYMENT_FOR_DEMOLITION,
							)
						)
					)
				{					
					throw new Exception('Выберите вид платежа');
				}				
				if(in_array($form->billing_bill_operation_type_id, array(Constant::BILLING_BILL_OPERATION_TYPE_RETURN_CONTRACTOR)) && !$form->contractor_id) {
					throw new Exception('Выберите подрядную организацию');
				}
				if(!(array_key_exists('cofinancing', $_POST))) {
					if(!(is_numeric($form->credit) && ($form->credit > 0))) {
						throw new Exception('Некорректная сумма операции');
					}
				}
				if($form->billing_bill_operation_type_id == Constant::BILLING_BILL_OPERATION_TYPE_DEBIT_KR) {
					if(!(is_numeric($form->debit) && ($form->debit > 0))) {
						throw new Exception('Некорректная сумма операции');
					}
				}
				$allowClosePeriod = Service::Billing_Period()->allowAct($this->user->getSessionId(), date('Y-m-d', strtotime($form->date)));
				// Проверка на закрытый период
				if(!$allowClosePeriod){
					throw new Exception ('Период закрыт! Выберите другую дату проводки.',950);
				}
				if(!in_array($form->billing_bill_operation_type_id, array(Constant::BILLING_BILL_OPERATION_TYPE_RETURN_CONTRACTOR, Constant::BILLING_BILL_OPERATION_TYPE_DEBIT_KR))) {
					if($form->billing_bill_spec_id) {
						$sort = new Sort('sr234124t', 'id', 'desc');
						$filter = new Type_Billing_Bill_Spec_Extended(array('id' => $form->billing_bill_spec_id));
						$pg = new Paginator('pg', 1);
						$rs = Service::Billing()->getSpecAccountList($pg, $sort, $filter, null);
					}elseif($form->billing_bill_ro_id) {
						$sort = new Sort('srtdfasdf314', 'id', 'desc');
						$filter = new Type_Billing_Bill_Ro_Extended(array('id' => $form->billing_bill_ro_id));
						$pg = new Paginator('pg', 1);
						$rs = Service::Billing()->getRoAccountList($pg, $sort, $filter, null);
					}
				}
				if(in_array($form->billing_bill_operation_type_id, array(Constant::BILLING_BILL_OPERATION_TYPE_RETURN_CONTRACTOR))) {
					$filter = new Type_Billing_Bill_Operation();
					$filter->home_id = $form->home_id;
					$filter->contractor_id = $form->contractor_id;
					$filter->billing_bill_operation_type_id = Constant::BILLING_BILL_OPERATION_TYPE_PAYMENT_CONTRACTOR;
					$summa = Service::Billing()->getSummaOperation($this->user->getSessionId(), $filter, 'credit');
					if($summa < $form->debit) {
						throw new Exception('Сумма к возврату превышает сумму перечисления');
					}					
					// ручное разнесение платежей
					$form->billing_bill_operation_method_id = 6;
					$form = Functions::cast($form, 'Type_Billing_Bill_Operation');
					$cofinancing_operation_ids = array();
					if(array_key_exists('cofinancing', $_POST)) {
						$cofinancing = (object)$_POST['cofinancing'];
						$filter = new Filter('flt4', new Type_Billing_Bill_Cofinancing_Filter);
						$filter->home_id = $form->home_id;
						$filter->program_year = $cofinancing->program_year;
						$sources_home = Service::Billing()->getCofinanicingOperation($this->user->getSessionId(), $filter);
						$arr_debit_source = array();
						foreach($sources_home as $r) {
							$arr_debit_source[$r->billing_bill_cofinancing_source_id] = $r->credit;
						}
						$item = Mikron_Functions::cast($cofinancing, 'Type_Billing_Bill_Cofinancing_Operation');
						$item->date = date('Y-m-d H:i:s');
						$item->billing_bill_cofinancing_type_id = Constant::BILLING_BILL_COFINANCING_RETURN_CONTRACTOR;
						$item->home_id = $form->home_id;
						$item->locality_id = Service::Address()->getLocalityIdByHomeId($form->home_id);
						$item->date_posting = $form->date;
						$item->credit_base = $form->credit_base;
						$item->contractor_id = isset($form->contractor_id) ? $form->contractor_id : null;
						if(property_exists($cofinancing, 'dolya') 
							&& $cofinancing->dolya == '1'
							&& property_exists($cofinancing, 'billing_bill_cofinancing_source_id')
							) {
							foreach($cofinancing->billing_bill_cofinancing_source_id as $cofinance => $summa) {
								$item->billing_bill_cofinancing_source_id = $cofinance;
								$item->debit = $summa;
								if(array_key_exists($cofinance, $arr_debit_source)) {
									if($summa > $arr_debit_source[$cofinance]) {
										throw new Exception('Сумма к возврату превышает сумму перечисления');
									}
								}else{
									throw new Exception('Некорректная операция');
								}
								$new_id = Mikron_Entity_Model::create('Type_Billing_Bill_Cofinancing_Operation', $item);
								$cofinancing_operation_ids[] = $new_id;
							}
						}
					}
					if(is_numeric($form->debit) && ($form->debit > 0)) {
						$resp = Service::Billing()->addBillOperation($this->user->getSessionId(), $form);
						if(count($cofinancing_operation_ids) && $resp) {
							Mikron_Entity_Model::update('Type_Billing_Bill_Cofinancing_Operation', $new_id, array('billing_bill_operation_id' => $resp), false);
						}
					}
				}elseif(in_array($form->billing_bill_operation_type_id, array(Constant::BILLING_BILL_OPERATION_TYPE_DEBIT_KR))) {
					// ручное разнесение платежей
					$form->billing_bill_operation_method_id = 6;
					$form = Functions::cast($form, 'Type_Billing_Bill_Operation');
					$new_id = Service::Billing()->addBillOperation($this->user->getSessionId(), $form);
					if(isset($_FILES['form'])) {
						$upload_dir_noid = dirname(__FILE__)."/../../../public/upload/billing/bill/operation";
						$upload_dir = $upload_dir_noid."/{$new_id}";
						if(!file_exists($upload_dir)) {
							mkdir($upload_dir, 0777, true);
						}
						$file_name = $_FILES['form']['name']['credit_document'];
						$file_name = str_replace('.php', '.bin', $file_name);
						$file_name = str_replace('/', null, $file_name);
						$file_name = str_replace('\\', null, $file_name);
						$file_path = $_FILES['form']['tmp_name']['credit_document'];
						$upload_dir = realpath($upload_dir);
						$full_file_name = "{$upload_dir}/{$file_name}";
						if(file_exists($full_file_name)) {
								unlink($full_file_name);
						}
						move_uploaded_file($file_path, $full_file_name);
						chmod($full_file_name, 0777);
						// $form[$file_code] = $file_name;
						$first_file = $file_name;
						$file_form['credit_document'] = $first_file;
						Mikron_Entity_Model::update('Type_Billing_Bill_Operation', $new_id, $file_form, false);
					}
				}else{
					if($rs) {
						if(count($rs->items)) {
							$rs = array_shift($rs->items);
							if($rs->total_amount < $form->credit) {
								throw new Exception('Сумма операции превышает сумму на расчетном счете');
							}
						}
					}
					if($form->billing_bill_operation_type_id != Constant::BILLING_BILL_OPERATION_TYPE_PAYMENT_CONTRACTOR) { // не проверять на текущий баланс дома при расчёте с подрядной организацией
						$home_balance = Service::Billing_Home()->getBillingExpenseAndDebit($this->user->getSessionId(), $form->home_id, date('Y-m-d'));
						if($form->credit > $home_balance->debit_sum) {
							throw new Exception('Сумма операции превышает сумму на текущем балансе дома');
						}
					}
					// ручное разнесение платежей
					$form->billing_bill_operation_method_id = 6;
					$form = Functions::cast($form, 'Type_Billing_Bill_Operation');
					$cofinancing_operation_ids = array();
					if(array_key_exists('cofinancing', $_POST)) {
						$cofinancing = (object)$_POST['cofinancing'];
						$filter = new Filter('flt4', new Type_Billing_Bill_Cofinancing_Filter);
						$filter->home_id = $form->home_id;
						$filter->program_year = $cofinancing->program_year;
						$sources_home = Service::Billing()->getCofinanicingOperation($this->user->getSessionId(), $filter);
						$arr_debit_source = array();
						foreach($sources_home as $r) {
							$arr_debit_source[$r->billing_bill_cofinancing_source_id] = $r->debit;
						}
						$item = Mikron_Functions::cast($cofinancing, 'Type_Billing_Bill_Cofinancing_Operation');
						$item->date = date('Y-m-d H:i:s');
						$item->billing_bill_cofinancing_type_id = Constant::BILLING_BILL_COFINANCING_CONTRACTOR;
						$item->home_id = $form->home_id;
						$item->locality_id = Service::Address()->getLocalityIdByHomeId($form->home_id);
						$item->date_posting = $form->date;
						$item->credit_base = $form->credit_base;
						$item->dictionary_item_id = $form->dictionary_item_id;
						$item->contractor_id = isset($form->contractor_id) ? $form->contractor_id : null;
						$item->billing_bill_payment_type_id = $form->billing_bill_payment_type_id;
						$first_file = null;
						$first_file_id = null;
						if(property_exists($cofinancing, 'dolya') 
							&& $cofinancing->dolya == '1'
							&& property_exists($cofinancing, 'billing_bill_cofinancing_source_id')
							) {
							foreach($cofinancing->billing_bill_cofinancing_source_id as $cofinance => $summa) {
								$item->billing_bill_cofinancing_source_id = $cofinance;
								$item->credit = $summa;
								if(array_key_exists($cofinance, $arr_debit_source)) {
									if($summa > $arr_debit_source[$cofinance]) {
										throw new Exception('Сумма операции превышает сумму финансирования');
									}
								}else{
									throw new Exception('Некорректная операция');
								}
								$new_id = Mikron_Entity_Model::create('Type_Billing_Bill_Cofinancing_Operation', $item);
								$cofinancing_operation_ids[] = $new_id;
								// Для каждого источника софинансирования записываем один и тот же файл
								if(isset($_FILES['form'])) {
									$upload_dir_noid = dirname(__FILE__)."/../../../public/upload/billing/bill/cofinancing/operation";
									$upload_dir = $upload_dir_noid."/{$new_id}";
									if(!file_exists($upload_dir)) {
										mkdir($upload_dir, 0777, true);
									}
									if($first_file_id) {
										$upload_dir = realpath($upload_dir);
										$full_file_name = "{$upload_dir}/{$first_file}";
										if(file_exists($full_file_name)) {
												unlink($full_file_name);
										}
										copy($upload_dir_noid."/{$first_file_id}/{$first_file}", $full_file_name);
										chmod($full_file_name, 0777);
									}elseif(array_key_exists('credit_document', $_FILES['form']['name']) && !$first_file) {
										$file_name = $_FILES['form']['name']['credit_document'];
										$file_name = str_replace('.php', '.bin', $file_name);
										$file_name = str_replace('/', null, $file_name);
										$file_name = str_replace('\\', null, $file_name);
										$file_path = $_FILES['form']['tmp_name']['credit_document'];
										$upload_dir = realpath($upload_dir);
										$full_file_name = "{$upload_dir}/{$file_name}";
										if(file_exists($full_file_name)) {
												unlink($full_file_name);
										}
										move_uploaded_file($file_path, $full_file_name);
										chmod($full_file_name, 0777);
										// $form[$file_code] = $file_name;
										$first_file = $file_name;
										$first_file_id = $new_id;
									}
									$file_form['credit_document'] = $first_file;
									Mikron_Entity_Model::update('Type_Billing_Bill_Cofinancing_Operation', $new_id, $file_form, false);
								}
							}
						}
					}
					if(is_numeric($form->credit) && ($form->credit > 0)) {
						$resp = Service::Billing()->addBillOperation($this->user->getSessionId(), $form);
						if(count($cofinancing_operation_ids) && $resp) {
							Mikron_Entity_Model::update('Type_Billing_Bill_Cofinancing_Operation', $new_id, array('billing_bill_operation_id' => $resp), false);
						}
					}
				}
			}
            echo json_encode(array('status' => 'success', 'message' => ''));
        } catch (Exception $ex) {
            echo json_encode(array('status' => 'error', 'message' => $ex->getMessage()));
        }
    }
	
	/**
	 * @ajax
	 */
	public function gethomebalanceAction(){
		$home_id = $this->_getParam('id');
		$contractor_id = $this->_getParam('contractor_id');
		if(!$home_id){
			throw new Exception('Неверный идентификатор', 950);
		}
		try {
			if($contractor_id) {
				$filter = new Type_Billing_Bill_Operation();
				$filter->home_id = $home_id;
				$filter->contractor_id = $contractor_id;
				$filter->billing_bill_operation_type_id = Constant::BILLING_BILL_OPERATION_TYPE_PAYMENT_CONTRACTOR;
				$summa = Service::Billing()->getSummaOperation($this->user->getSessionId(), $filter, 'credit');
				$filter->billing_bill_operation_type_id = Constant::BILLING_BILL_OPERATION_TYPE_RETURN_CONTRACTOR;
				$summa2 = Service::Billing()->getSummaOperation($this->user->getSessionId(), $filter, 'debit');
				$summa -= $summa2;
				$summa = max($summa, 0);
				$summa = Functions::number_format($summa, 2, ',', ' ');
				echo json_encode(array('status' => 'success', 'message' => '', 'summa' => $summa));
			}else{
				$home_balance = Service::Billing_Home()->getBillingExpenseAndDebit($this->user->getSessionId(), $home_id, date('Y-m-d'));
				$home_balance->current_balance = $home_balance->debit_sum - $home_balance->credit_sum;
				foreach ($home_balance as $home_balance_index => $home_balance_item) {
					$home_balance->$home_balance_index = Functions::number_format($home_balance_item, 2, ',', ' ').' руб.';
				}
				echo json_encode(array('status' => 'success', 'message' => '', 'home_balance' => $home_balance));
			}
		} catch (Exception $ex) {
            echo json_encode(array('status' => 'error', 'message' => $ex->getMessage()));
        }
	}
	
	/**
	 * @ajax
	 */
	public function checkoperationAction(){
		$id = $this->_getParam('id');
		$billing_bill_ro_id = $this->_getParam('billing_bill_ro_id');
		$billing_bill_spec_id = $this->_getParam('billing_bill_spec_id');
		if(!$id && (!$billing_bill_ro_id || !$billing_bill_spec_id)){
			throw new Exception('Неверный идентификатор', 950);
		}
		$filter = array(
			'billing_bill_operation_type_id' => Constant::BILLING_BILL_OPERATION_TYPE_DEBIT_KR, 
			'billing_bill_operation_method_id' => Constant::BILLING_BILL_OPERATION_METHOD_BANKPACKET, 
			'billing_bank_packet_operation_id' => new Mikron_Entity_Filter_IsNotnull(0),
			'id' => $id, 
		);
		if($billing_bill_ro_id) {
			$filter['billing_bill_ro_id'] = $billing_bill_ro_id;
		}elseif($billing_bill_spec_id) {
			$filter['billing_bill_spec_id'] = $billing_bill_spec_id;
		}
		$operation = new Type_Billing_Bill_Operation(null, $filter);
		if($operation->id) {
			$operation->date = date('d.m.Y', strtotime($operation->date));
			echo json_encode(array('status' => 'success', 'message' => '', 'operation' => $operation));
		} else {
            echo json_encode(array('status' => 'error', 'message' => 'Операция не найдена'));
        }
	}

    /**
     * Новое поступление/возврат в бюджет
     *
     * @ajax
     */
    public function cofinancingAction()
    {
        try {
            if ($this->_request->isPost()) {
                $request = $this->_request->getPost();
                $data = $request['form'];
                $documents = !empty($request['document']) ? $request['document'] : array();

                $upload = new \Etton\Component\File\Transfer\Adapter\Http();

                $uploadPath = $this->getInvokeArg('bootstrap')->getOption('paths');
                $uploadPath = rtrim($uploadPath['cofinancing']['upload'], "/");

                $dir = $uploadPath . "/%s/";
                $dir = sprintf($dir, date("Y/m/d"));

                $files = $upload->getFileInfo();
                foreach ($files as $file => $info) {
                    $name = $upload->getFileName($file);
                    $name = Functions::translit($name);
                    $name = str_replace(" ", "_", $name);

                    $filter = new \Etton\Component\Filter\File\Rename(
                        array('target' => $dir . basename($name), 'overwrite' => false)
                    );
                    $upload->addCustomFilter($filter, null, $file);

                    if (!$upload->isUploaded($file)) {
                        throw new \RuntimeException("Ошибка при загрузке файла " . basename($name));
                    }

                    if (!$upload->isValid($file)) {
                        throw new \RuntimeException("Недопустимый формат у файла " . basename($name));
                    }
                }

                if (count($files)) {
                    // prepare directory and upload
                    \Etton\Util\FileSystem::createFolder($dir);

                    if (!$upload->receive()) {
                        throw new \RuntimeException("Ошибка загрузки файлов");
                    }

                    $configPaths = $this->getInvokeArg('bootstrap')->getOption('paths');
                    $uploadPath = realpath($configPaths['default']['upload']);

                    $counter = 0;
                    foreach ($upload->getFileInfo() as $file => $fileInfo) {
                        $documents[$counter++]['document_file'] = Functions::translit(str_replace($uploadPath, "", realpath($fileInfo['tmp_name'])));
                    }
                }

                $service = new \Etton\Service\Billing\Cofinancing\Operation();
                $service->addOperation($data, $documents);
            } else {
                throw new \RuntimeException("Неправильный вызов метода");
            }
            echo json_encode(array('status' => 'success', 'message' => ''));
        } catch (\Exception $e) {
            echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
        }
    }
}