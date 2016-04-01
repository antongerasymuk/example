
<?php
// BEGIN init
define('SYS_TYPE', 'web');
define('SYS_PREFIX', '/site');
define('URL_PREFIX', '');
define('USER_PREFIX', 'users');
include dirname(__FILE__).'/include/init.php';
ignore_user_abort(false);


class Statistic {
	static  $ppShops = "142,36241,41380,39638,39456,32990";
	public static function getUpdatedData() {
		global $GB;
		$updatedDataSQL = "SELECT  year, month, data FROM service_stats";
		$updatedData = $GB->db->getAll($updatedDataSQL);
		foreach ($updatedData as $du) {
			$dateStr = sprintf("%02d",$du['year'])."<br/>".str_pad($du['month'], 2, '0', STR_PAD_LEFT);
			$allData[$dateStr] = unserialize($du['data']);
		}
		krsort($allData);

		return $allData;
	}

	public static function getDatesToProcess($startingMonth, $startingYear) {
		global $GB;
		$completedDataSQL="SELECT year,month,data FROM service_stats";
		$completedData=$GB->db->getAll($completedDataSQL);

		// check for year input
		// get month-year pairs from $startingMonth.$startingYear to this year
		$datesList = array();
		for ($year = date("Y"); $year >= $startingYear; $year--) {
			for ($month = 12; $month >= 1; $month--) {

				// ignore monthes before 11.2010
				if ($year == $startingYear) {
					if ($month < $startingMonth) {
						continue;
					}
				}

				// do not add future monthes of this year
				if ($year == date("Y")) {
					if ($month > date("n")) {
						continue;
					}
				}
				//check date in base
				foreach ($completedData as $dfb) {
					if (($year == $dfb['year']) && ($month == $dfb['month'])) {
						continue 2;
					}
				}

				$datesList[] = array(
					"year" => $year,
					"month" => $month
				);
			}
		}

		return $datesList;
	}

	public static function getTotalOrders($sqlDateRow) {

		global $GB;

		$totalCountSQL = "SELECT
		COUNT(*)
			AS total_orders,
		SUM(IF(o.id_shops IN (" . self::$ppShops . "),1, 0))
			AS total_orders_pp,
		SUM(IF(o.id_shops IN (" . self::$ppShops . "),0, 1))
			AS total_orders_partners,
		SUM(IF(o.c_country = 'ua',0, 1))
			AS total_orders_ua,
		SUM(IF(o.c_country = 'ru',0, 1)) AS total_orders_ru
		FROM
			orders o
		WHERE
			o.c_dt " . $sqlDateRow;

		$totalCountRaw = $GB->db->getAll($totalCountSQL);
		$totalCountRaw = $totalCountRaw[0];
		$dataRow['total_orders'] = $totalCountRaw['total_orders'];
		$dataRow['total_orders_pp'] = $totalCountRaw['total_orders_pp'];
		$dataRow['total_orders_partners'] = $totalCountRaw['total_orders_partners'];
		$dataRow['total_orders_ru'] = $totalCountRaw['total_orders_ru'];
		$dataRow['total_orders_ua'] = $totalCountRaw['total_orders_ua'];

		return $dataRow;
	}

	public static function getCompletedOrders($completedFrom) {

		global $GB;
		$completedSQL = "SELECT
					COUNT(*) AS completed_orders,
					SUM((SELECT SUM(op.qty) FROM orders_products op WHERE op.id_orders = o.id)) AS completed_products,
					SUM(o.cost_products + o.cost_delivery + o.cost_services + o.cost_coupons) AS completed_cost,
					SUM((SELECT SUM(royalty * qty) FROM orders_products WHERE id_orders = o.id)) AS completed_royalty,
					SUM((SELECT SUM(`sum`) FROM shops_payments WHERE id_orders = o.id AND `type` IN ('referral_royalty', 'refund_referral_royalty', 'bonus', 'refund_bonus'))) AS completed_royalty_other,
					SUM(o.cost_products + o.cost_services) AS completed_profit".$completedFrom;
		$completedRaw = $GB->db->getAll($completedSQL);
		$completedRaw = $completedRaw[0];
		$dataRow['completed_orders'] = $completedRaw['completed_orders'];
		$dataRow['completed_products'] = $completedRaw['completed_products'];
		$dataRow['completed_cost'] = $completedRaw['completed_cost'];
		$dataRow['completed_royalty'] = $completedRaw['completed_royalty'] + $completedRaw['completed_royalty_other'];
		$dataRow['completed_profit'] = $completedRaw['completed_profit'] - $dataRow['completed_royalty'];


		$additionalWhere = " o.c_country = 'ua'";
		$getSpecialOrderUA = self::getSpecialOrder('completed_orders_ua', 'completed_cost_ua', $completedFrom, $additionalWhere);
		$dataRow = array_merge($dataRow, $getSpecialOrderUA);

		$additionalWhere = "  o.c_country = 'ru'";
		$getSpecialOrderRU = self::getSpecialOrder('completed_orders_ru', 'completed_cost_ru', $completedFrom, $additionalWhere);
		$dataRow = array_merge($dataRow, $getSpecialOrderRU);

		$additionalWhere = " o.id_shops IN (" . self::$ppShops . ")";
		$getSpecialOrderPP = self::getSpecialOrder('completed_orders_pp', 'completed_cost_pp', $completedFrom, $additionalWhere);
		$dataRow = array_merge($dataRow, $getSpecialOrderPP);

		$additionalWhere = " o.id_shops NOT IN (" . self::$ppShops . ")";
		$getSpecialOrderPartner = self::getSpecialOrder('completed_orders_partner', 'completed_cost_partner', $completedFrom, $additionalWhere);
		$dataRow = array_merge($dataRow, $getSpecialOrderPartner);

		return $dataRow;
	}
	private static function getSpecialOrder($ordersKey, $costKey, $completedFrom, $additionalWhere) {
		global $GB;
		$query = "SELECT
			COUNT(*) AS ".$ordersKey.",
			SUM(o.cost_products + o.cost_delivery + o.cost_services + o.cost_coupons) AS ".$costKey;
		$query .= $completedFrom." AND ".$additionalWhere;
		$SpecialOrder = $GB->db->getAll($query);
		$SpecialOrder= $SpecialOrder[0];

		return $SpecialOrder;
	}
	public static function getPayouts($sqlDateRow) {
		global $GB;
		$payoutsSQL = "SELECT
				-1 * SUM(sp.sum) AS payout
			FROM
				shops_payments sp
			WHERE
				sp.type = 'payout' AND sp.dt " . $sqlDateRow;
		$payoutsRaw = $GB->db->getAll($payoutsSQL);
		$payoutsRaw = $payoutsRaw[0];
		$dataRow['payments'] = $payoutsRaw['payout'];

		return $dataRow;
	}

	public static function insertNewData($date, $dateStr, $dataRow) {
		global $GB;
		$allData = array();
		if ((($date['year'] == date('Y')) && (($date['month']) == date('n')))) {
			$allData[$dateStr] = $dataRow;
		} else {
			$checkDateSQL = "SELECT id FROM service_stats WHERE year=" . $date['year'] . " AND month=" . $date['month'] . " LIMIT 1";
			$checkDate = $GB -> db ->getAll($checkDateSQL);
			$checkDate = $checkDate[0];

			if (!isset($checkDate)) {

				$query = "INSERT
				INTO
					service_stats (year, month, data)
				VALUES
					('" . $date['year'] . "', '" . $date['month'] . "', '" . serialize($dataRow) . "')";
				$GB->db->beginTransaction();
				try {
					$GB->db->query($query);
				} catch (Exception $e) {
					$GB->db->rollback();
					$GB->M->put('Database error: ' . $e->getMessage());
				}
				$GB->db->commit();
			}
		}

		return $allData;
	}
	public static function getAllData ($datesList) {
		$allData = array();
		foreach ($datesList as $d) {
			$dateStr = sprintf( "%02d",$d['year'])."<br/>".str_pad( $d['month'], 2, '0', STR_PAD_LEFT );
			$dataRow = array();
			// echo "--- new row --- <br/>";
			$sqlDateFrom = $d['year'] . "-" . sprintf("%02d",$d['month']) . "-01 00:00:00";
			$sqlDateTo = $d['year'] . "-" . sprintf("%02d",$d['month']) . "-31 23:59:59";
			$sqlDateRow = " BETWEEN '" . $sqlDateFrom . "' AND '" . $sqlDateTo . "' ";
			$completedFrom = " FROM orders_statuses os
						JOIN orders o
							ON o.id = os.id_orders
							AND o.status <> 'null'
						JOIN deliveries d
							ON d.id = o.id_deliveries
					WHERE
						(
							(
								os.status = 'conf_pay_preorder' AND
								d.postpay = 0
							) OR (
								os.status = 'complete' AND
								d.postpay = 1
							)
						) AND os.dt " . $sqlDateRow;
			// add total and completed orders
			$dataRow = array_merge($dataRow, self::getTotalOrders($sqlDateRow), self::getCompletedOrders($completedFrom), self::getPayouts($sqlDateRow));

			// ToDo for all row: if zero then set mdash

			if ($d['month'] == 12) {
				$css = 'style="border-top: 3px solid #999;"';
			} else {
				$css = '';
			}
			$dataRow = array_merge($dataRow, array('css' => $css));
			$allData = array_merge($allData, self::insertNewData($d, $dateStr, $dataRow));
		}
		$allData = array_merge($allData, self::getUpdatedData());

		return $allData;
	}
}

// check for year input
$startingYear = 2010;
$startingMonth = 11;
$datesList = Statistic::getDatesToProcess($startingMonth, $startingYear);

//get statistic
$allData=Statistic::getAllData($datesList);
?>

<!DOCTYPE html>

<html>
<head>
	<title>PP - Статистика</title>
	<meta name="viewport" content="user-scalable=yes, width=device-width">
	<meta name="description" content="Все хорошо, прекрасная маркиза!">
	<script type="text/javascript" src="http://static.prostoprint.com/js/jquery.js"></script>
	<script type="text/javascript" src="http://static.prostoprint.com/js/chart.min.js"></script>
</head>

<body>
<style type="text/css">
	html, body {
		padding: 0px;
		margin: 0px;
		background-color: #999;
	}
	table { 
		width: 100%;
		table-layout: fixed;
		border-collapse: collapse;
		background:#000000; 
		font:normal 11px Arial, Helvetica, sans-serif; 
	}
	table.body {
		margin-top: 42px;
	}
	table.head {
		width: 1262px;
		position: fixed;
		top: 0px;
	}
	td {
		text-align:center; 
		background:#fff; 
		border: 1px solid #999;
	}
	thead td { 
		font-weight:bold; 
		padding:3px 4px; 
	}
	tbody td { 
		padding:3px 4px; 
	}
	tr:nth-child(2n) td, thead td { 
		background:#f5f5f5; 
	}
	.brt {
		border-right: 2px solid #999;
	}
	.blt {
		border-left: 2px solid #999;
	}
	.arrow {
		float: right;
		cursor: pointer;
		color: red;
		font-size: 22px;
		line-height: 12px;
	}
	.arrow:hover {
		padding-right: 3px;
		margin-left: -3px;
	}
	.outer {
		width:1262px; 
	}
	.all {
		/*background-color: rgba(255, 255, 175 , 1); !important*/
	}
	.done {
		/*background-color: rgba(175, 255, 175, 1); !important*/
		font-weight: bold;
	}
	</style>
<script type="text/javascript">
jQuery(function($) {
	$(".arrow").toggle(
	function() {
		$(".extra").hide();
		$(".outer").css("width", 506);
		$("table.head").css("width", 506);
		$(".arrow").html("&#8680;");
		$(".arrow").css("color", "green");

	},
	function() {
		$(".extra").show();
		$(".outer").css("width", 1262);
		$("table.head").css("width", 1262);
		$(".arrow").html("&#8678;");
		$(".arrow").css("color", "red");
	});
})
</script>
<div class="outer">
<table cellpadding="0" cellspacing="0" border="0" class="head">
	<thead>
		<tr>
			<td rowspan="2" >Дата</td>
			<td colspan="3" class="brt blt">Заказы<span class="arrow">&#8678;</span></td>
			<td colspan="3" class="brt extra">UA</td>
			<td colspan="3" class="brt extra">RU</td>
			<td colspan="3" class="brt extra">ПП</td>
			<td colspan="3" class="brt extra">Партнеры</td>
			<td rowspan="2" >К-во товаров</td>
			<td rowspan="2" >Прибыль</td>
			<td rowspan="2" >Роялти</td>
			<td rowspan="2" >Выплата роялти</td>
		</tr>
		<tr>
			<td class="all">все</td>
			<td class="done">вып.</td>
			<td class="brt">&#8721;</td>
			<td class="extra all">все</td>
			<td class="extra" >вып.</td>
			<td class="brt extra">&#8721;</td>
			<td class="extra all" >все</td>
			<td class="extra" >вып.</td>
			<td class="brt extra">&#8721;</td>
			<td class="extra all" >все</td>
			<td class="extra" >вып.</td>
			<td class="brt extra">&#8721;</td>
			<td class="extra all" >все</td>
			<td class="extra" >вып.</td>
			<td class="brt extra">&#8721;</td>
		</tr>
	</thead>
</table>

<table cellpadding="0" cellspacing="0" border="0" class="body">
	<tbody>
		<?php foreach ($allData as $k => $row) : ?>
		<tr <?php echo $row['css'] ?>>
			<td class="brt" ><?php echo $k; ?></td>
			<td class="all"><?php echo $row['total_orders']; ?></td>
			<td class="done"><?php echo $row['completed_orders']; ?></td>
			<td class="brt" ><?php echo $row['completed_cost']; ?></td>
			<td class="extra all" ><?php echo $row['total_orders_ua']; ?></td>
			<td class="extra done" ><?php echo $row['completed_orders_ua']; ?></td>
			<td class="brt extra" ><?php echo $row['completed_cost_ua']; ?></td>
			<td class="extra all" ><?php echo $row['total_orders_ru']; ?></td>
			<td class="extra done"  ><?php echo $row['completed_orders_ru']; ?></td>
			<td class="brt extra" ><?php echo $row['completed_cost_ru']; ?></td>
			<td class="extra all" ><?php echo $row['total_orders_pp']; ?></td>
			<td class="extra done" ><?php echo $row['completed_orders_pp']; ?></td>
			<td class="brt extra" ><?php echo $row['completed_cost_pp']; ?></td>
			<td class="extra all" ><?php echo $row['total_orders_partners']; ?></td>
			<td class="extra done" ><?php echo $row['completed_orders_partner']; ?></td>
			<td class="brt extra" ><?php echo $row['completed_cost_partner']; ?></td>
			<td ><?php echo $row['completed_products']; ?></td>
			<td ><?php echo $row['completed_profit'] ?></td>
			<td ><?php echo $row['completed_royalty']; ?></td>
			<td ><?php echo $row['payments']?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
</div>
</body>
</html>

