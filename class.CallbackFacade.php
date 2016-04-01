<?php

class CallbackFacade extends AbstractFacade {

	public $obj_callback_form = array(
		'phone' => OBJ_STRING,
	);

	function __construct(&$core) {
		parent::__construct($core);

		global $GB;
		$this->ad = $GB->ad("Callback");
	}

	function addCallbackRequest() {
		global $GB;
		header("Access-Control-Allow-Origin: *");
		$R = new PP_result();
		$data = $GB->getObject($this->obj_callback_form, $_REQUEST);
		if ((!isset($data['phone'])) || (trim($data['phone']) == '')) {
			$R->registerError("Пожалуйста, введите телефон");
			echo $R->getResult('json');
			return;
		}
		
		$phone = $data['phone'];
		$phone = preg_replace("/[^0-9\+\-\(\)]/", "", $phone);
		$ip = isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR'];
		$hasRequest = $this->ad->getByFilter(array('phone' => $phone, 'status' => 'pending'));

		$newRequest = array(
			"c_dt" => date('Y-m-d H:i:s'),
			"ip" => $ip,
			"phone" => $phone,
			"status" => "pending",
			"id_admins" => null,
			"comment" => "",
		);

		if (!$hasRequest) {
			try {
				$this->ad->insert($newRequest);
			} catch (Exception $e) {
				$R->registerError("Произошла ошибка, обратитесь пожалуйста в поддержку!");
			}
		}
		if (!$R->hasErrors()) {
			$R = $this->updateDataFile();
		}
		echo $R->getResult('json');
		return;
	}

    function updateDataFile() {
        global $GB;
		$res = new PP_Result();
        try {
            // do not fetch finished requests
            $data = $GB->ad('Callback')->getByFilter(
                array(
                    "status_in" => array("pending", "calling", "busy", "no_answer")
                ),
                array(
                    "c_dt"
                )

            );
        } catch (Exception $e) {
            $res->registerError('Database error: ' . $e->getMessage());
            return $res;
        }
        $result = array();
        $result['items'] = $data;
        $result['timestamp'] = round(microtime(true) * 1000);
        $res->setData($result);

        file_put_contents($GB->paths['root'] . "/admin/callback_requests.js", json_encode($res->data), LOCK_EX);
        return $res;
    }

}
