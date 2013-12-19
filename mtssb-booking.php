<?php
/**
 * MTS Simple Booking データベースアクセスモジュール
 *
 * @Filename	mtssb-booking.php
 * @Date		2012-05-01
 * @Author		S.Hayashi
 *
 * Updated to 1.2.0 on 2012-12-23
 * Updated to 1.1.5 on 2012-12-03
 */

class MTSSB_Booking {
	const VERSION = '1.2.0';

	const BOOKING_TABLE = 'mtssb_booking';
	const TABLE_VERSION = '1.1';

	const USER_ADJUSTED = -1;

	/**
	 * Common private valiable
	 */
	protected $domain;
	protected $plugin_url;

	// Table names
	protected $tblBooking;

	// オプション形式を操作する元のオブジェクト
	protected $option = null;

	// 内部処理用のデータ構造(予約編集データ格納)
	protected $booking = array();

	// DB保存データ形式として格納
	private $record = array();

	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		global $wpdb, $mts_simple_booking;

		$this->domain = MTS_Simple_Booking::DOMAIN;
		$this->plugin_url = $mts_simple_booking->plugin_url;

		$this->tblBooking = $wpdb->prefix . self::BOOKING_TABLE;

		$this->_install_table();
	}

	/**
	 * 予約項目別月間予約件数データを取得
	 *
	 * @stay		Y-m-d
	 * $return		array(unixtime => array(article_id => array(rcount, rnumber)));
	 */
	public function get_reserved_count($year, $month) {
		global $wpdb;

		$sql = "
			SELECT (booking_time - booking_time % 86400) AS booking_daytime,article_id,count(booking_time) AS rcount,sum(number) AS rnumber
			FROM $this->tblBooking
			WHERE booking_time>=" . mktime(0, 0, 0, $month, 1, $year) . " AND booking_time<" . mktime(0, 0, 0, $month + 1, 1, $year) . "
			GROUP BY booking_daytime,article_id
			ORDER BY booking_daytime,article_id";

		$booking = $wpdb->get_results($sql, ARRAY_A);

		$reserved = array();
		foreach ($booking as $daybook) {
			$reserved[$daybook['booking_daytime']][$daybook['article_id']] = array('count' => $daybook['rcount'], 'number' => $daybook['rnumber']);
		}

		return $reserved;
	}

	/**
	 * 指定日付の予約件数データを取得
	 *
	 * @daytime		unix time
	 * $return		array(unixtime => array(article_id => array(rcount, rnumber)));
	 */
	public function get_reserved_day_count($daytime) {
		global $wpdb;

		$bookings = $wpdb->get_results($wpdb->prepare("
			SELECT booking_time,article_id,count(booking_time) AS rcount,sum(number) AS rnumber
			FROM $this->tblBooking
			WHERE booking_time>=%d AND booking_time<%d
			GROUP BY booking_time,article_id
			ORDER BY booking_time,article_id", $daytime, $daytime + 86400), ARRAY_A);

		$reserved = array();
		foreach ($bookings as $booking) {
			$reserved[$booking['booking_time']][$booking['article_id']] = array('count' => $booking['rcount'], 'number' => $booking['rnumber']);
		}

		return $reserved;
	}

	/**
	 * 全予約数を戻す(booking_list用)
	 *
	 *
	 */
	public function get_booking_count() {
		global $wpdb;

		$number = $wpdb->get_col("SELECT count(*) FROM $this->tblBooking WHERE user_id<>-1");

		return intval($number[0]);
	}

	/**
	 * 指定月間の予約数を戻す(booking_list用)
	 *
	 */
	public function get_booking_count_monthly($year, $month) {
		global $wpdb;

		$number = $wpdb->get_col($wpdb->prepare("
			SELECT count(*) FROM $this->tblBooking
			WHERE booking_time>=%d AND booking_time<%d AND user_id<>-1",
			 mktime(0, 0, 0, $month, 1, $year), mktime(0, 0, 0, $month + 1, 1, $year)));

		return intval($number[0]);
	}

	/**
	 * 指定日の予約データを取得する
	 *
	 * @daytime
	 */
	public function get_booking_of_theday($daytime, $article_ids='') {
		global $wpdb;

		$conditions = '1=1';

		if (!empty($article_id)) {
			$conditions = "article_id in ($article_ids)";
		}

		$data = $wpdb->get_results($wpdb->prepare("
			SELECT booking_id,booking_time,confirmed,parent_id,article_id,user_id,number,options,client,created
			FROM $this->tblBooking
			WHERE booking_time>=%d AND booking_time<%d AND %s
			ORDER BY article_id ASC, booking_time ASC", $daytime, $daytime + 86400, $conditions), ARRAY_A);

		foreach ($data as $key => $booking) {
			$data[$key]['options'] = unserialize($booking['options']);
			$data[$key]['client'] = unserialize($booking['client']);
		}

		return $data;
	}

	/**
	 * 指定日時の予約データを取得する
	 *
	 * @daytime
	 */
	public function get_booking_of_thetime($thetime, $article_ids='') {
		global $wpdb;

		$conditions = '1=1';

		if (!empty($article_ids)) {
			$conditions = "article_id in ($article_ids)";
		}

		$data = $wpdb->get_results($wpdb->prepare("
			SELECT booking_id,booking_time,confirmed,parent_id,article_id,user_id,number,options,client,created
			FROM $this->tblBooking
			WHERE booking_time=%d AND $conditions
			ORDER BY article_id ASC, booking_id ASC", $thetime), ARRAY_A);

		foreach ($data as $key => $booking) {
			$data[$key]['options'] = unserialize($booking['options']);
			$data[$key]['client'] = unserialize($booking['client']);
		}

		return $data;
	}

	/**
	 * 予約データを取得する
	 *
	 * @offset
	 * @limit
	 * @order
	 * @article_id
	 */
	public function get_booking_list($offset, $limit, $order, $conditions='1=1') { //article_id=0) {
		global $wpdb;

		//$conditions = 1 < intval($article_id) ? sprintf('article_id=%d', $article_id) : '1=1';
		

		$sql = $wpdb->prepare("
			SELECT booking_id,booking_time,confirmed,parent_id,article_id,user_id,number,options,client,created,
				Post.post_title AS article_name
			FROM $this->tblBooking
			JOIN {$wpdb->posts} AS Post ON article_id=Post.ID
			WHERE $conditions AND user_id<>-1
			ORDER BY {$order['key']} {$order['direction']}
			LIMIT %d, %d", $offset, $limit);

		$data = $wpdb->get_results($sql, ARRAY_A);

		foreach ($data as $key => $booking) {
			$data[$key]['options'] = unserialize($booking['options']);
			$data[$key]['client'] = unserialize($booking['client']);
		}

		return $data;
	}

	/**
	 * 予約データの読み込み
	 *
	 * @booking_id
	 * @return $bookingタイプ
	 */
	protected function get_booking($booking_id) {
		global $wpdb;

		$table = $wpdb->prefix . self::BOOKING_TABLE;

		$record = $wpdb->get_row($wpdb->prepare("
			SELECT * FROM $table
			WHERE booking_id=%s", intval($booking_id)), ARRAY_A);

		if (empty($record)) {
			return false;
		}

		$booking = $this->new_booking();

		$booking = $record;
		$booking['options'] = null;
		$booking['client'] = unserialize($record['client']);

		return $booking;
	}

	/**
	 * 予約の調整データを取得する
	 *
	 * @article_id
	 * @booking_time
	 */
	public function get_adjustment($article_id, $booking_time) {
		global $wpdb;

		$data = $wpdb->get_results($wpdb->prepare("
			SELECT *
			FROM $this->tblBooking
			WHERE article_id=%d AND booking_time=%d AND user_id=%d
			ORDER BY booking_id DESC", $article_id, $booking_time, self::USER_ADJUSTED), ARRAY_A);

		return $data;

	}

	/**
	 * 予約の調整処理
	 *
	 * @article_id
	 * @restriction		capacity or quantity
	 * @booking_time
	 * @number
	 */
	public function adjust_booking($article_id, $restriction, $booking_time, $number) {
		// 調整データを取り出す
		$adjustments = $this->get_adjustment($article_id, $booking_time);

		// 調整数が0で調整データあれば調整データを削除する
		if ($number <= 0) {
			if (!empty($adjustments)) {
				foreach ($adjustments as $booking) {
					if ($this->del_booking($booking['booking_id']) === false) {
						return false;
					}
				}
			}
			return true;
		}

		// 予約タイプが収容人数の調整
		if ($restriction == 'capacity') {
			if (empty($adjustments)) {
				$this->booking = $this->_new_adjustment($booking_time, $article_id);
				$this->booking['number'] = $number;
				if ($this->add_booking() === false) {
					return false;
				}
			} else {
				$this->booking = $adjustments[0];
				if ($this->booking['number'] != $number) {
					$this->booking['number'] = $number;
					if ($this->save_booking() === false) {
						return false;
					}
				}
			}
		}

		// 予約タイプが予約件数の調整
		else {
			$count = count($adjustments);
			// 予約調整データが多い場合は削除する
			if ($number < $count) {
				foreach ($adjustments as $key => &$booking) {
					if ($key < $count - $number) {
						if ($this->del_booking($booking['booking_id']) === false) {
							return false;
						}
					}
				}
			}
			// 予約調整データが少ない場合は追加する
			else if ($count < $number) {
				for ($i = $number - $count; 0 < $i; $i--) {
					$this->booking = $this->_new_adjustment($booking_time, $article_id);
					if ($this->add_booking() === false) {
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * 調整データを取得する
	 *
	 */
	protected function _new_adjustment($booking_time, $article_id) {
		$booking = $this->new_booking($booking_time, $article_id);
		$booking['user_id'] = self::USER_ADJUSTED;
		$booking['confirmed'] = 1;
		$booking['client'] = null;
		return $booking;
	}

	/**
	 * 予約データの新規追加
	 *
	 */
	public function add_booking() {
		global $wpdb;

		$this->_recordData();
		$this->record['created'] = current_time('mysql');

		$result = $wpdb->insert($this->tblBooking, $this->record,
			array('%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s'));

		if (!$result) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Save booking data
	 *
	 */
	public function save_booking() {
		global $wpdb;

		$this->_recordData();

		$where = array('booking_id' => $this->record['booking_id']);
		unset($this->record['booking_id']);

		$result = $wpdb->update($this->tblBooking, $this->record, $where,
			array('%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s'), array('%d'));

		if (!$result) {
			return false;
		}

		return $this->booking['booking_id'];
	}

	/**
	 * Delete booking data
	 *
	 */
	public function del_booking($id=0) {
		global $wpdb;

		$condition = 'booking_id=' . intval($id);

		$result = $wpdb->query("
			DELETE FROM {$this->tblBooking}
			WHERE " . $condition);

		return $result;
	}

	/**
	 * 入力データを正規化し、bookingオブジェクトデータにして戻す
	 *
	 * @post		管理画面 入力postデータ
	 * @timeflg		true:管理画面 false:フロントフォーム
	 * @rate		人数カウントレートの配列
	 *
	 * @return		bookingデータを戻す
	 */
	public function normalize_booking($post, $count=array()) {

		if (get_magic_quotes_gpc()) {
			$post = stripslashes_deep($post);
		}

		$booking = $this->new_booking();

		// 入力データの正規化
		if (isset($post['booking_id'])) {
			$booking['booking_id'] = intval($post['booking_id']);
		}

		if (isset($post['booking_time'])) {
			$booking['booking_time'] = intval($post['booking_time']);
		} else {
			$booking['booking_time'] = mktime(0, 0, 0, $post['month'], $post['day'], $post['year']) + intval($post['timetable']);
		}

		if (isset($post['article_id'])) {
			$booking['article_id'] = intval($post['article_id']);
		}

		if (isset($post['user_id'])) {
			$booking['user_id'] = intval($post['user_id']);
		}

		if (isset($post['number'])) {
			$booking['number'] = intval(trim(mb_convert_kana($post['number'], 'as')));
		}

		if (isset($post['confirmed'])) {
			$booking['confirmed'] = intval($post['confirmed']);
		}

		// オプションデータ
		if (isset($post['options'])) {
			foreach ($booking['options'] as $option) {
				$keyname = $option->getKeyname();
				if (isset($post['options'][$keyname])) {
					$option->normalize($post['options'][$keyname]);
				}
			}
		}

		// クライアントデータ
		$client = &$booking['client'];
		foreach ($client as $keyname => $val) {
			if (!isset($post['client'][$keyname])) {
				continue;
			}

			switch ($keyname) {
				case 'course' ://コース追加
				case 'option' :
				case 'option1' :
				case 'option2' :
				case 'option3' :
				case 'option4' :
				case 'option5' :
				case 'option6' :
				case 'option7' :
				case 'option8' :
				case 'option9' :
				case 'company' :
				case 'email' :
				case 'postcode' :
				case 'address1' :
				case 'address2' :
				case 'tel' :
					$client[$keyname] = trim(mb_convert_kana($post['client'][$keyname], 'as'));
					break;
				case 'adult' :
				case 'child' :
				case 'baby' :
				case 'car' :
					$client[$keyname] = intval(trim(mb_convert_kana($post['client'][$keyname], 'as')));
					break;
				case 'name' :
					$client['name'] = trim(mb_convert_kana($post['client']['name'], 's'));
					break;
				case 'furigana' :
					$client['furigana'] = trim(mb_convert_kana($post['client']['furigana'], 'asKCV'));
					break;
				case 'birthday' :
					$client['birthday']->normalize($post['client']['birthday']);
					break;
				case 'gender' :
					if (!empty($post['client']['gender'])) {
						$client['gender'] = $post['client']['gender'] == 'male' ? 'male' : 'female';
					}
					break;
				default:
					break;
			}
		}

		if (!empty($count)) {
			$booking['number'] = intval($client['adult']) * $count['adult']
			 + intval($client['child']) * $count['child']
			 + intval($client['baby']) * $count['baby'];
		}

		$booking['note'] = mb_substr(trim($post['note']), 0, 500);

		return $booking;
	}

	/**
	 * bookingデータをテーブルに登録するデータ形式に変換する
	 *
	 *
	 */
	 protected function _recordData() {
	
		$record = array();

		$record['booking_id'] = $this->booking['booking_id'];
		$record['booking_time'] = $this->booking['booking_time'];
		$record['confirmed'] = $this->booking['confirmed'];
		$record['parent_id'] = $this->booking['parent_id'];
		$record['article_id'] = $this->booking['article_id'];
		$record['user_id'] = $this->booking['user_id'];
		$record['number'] = $this->booking['number'];
		$record['options'] = null;
		if (is_null($this->booking['client']) || $this->booking['user_id'] == self::USER_ADJUSTED) {
			$record['client'] = null;
		} else {
			$client = $this->booking['client'];
			$client['birthday'] = '';
			$record['client'] = serialize($client);
		}
		$record['note'] = $this->booking['note'];

		$this->record = $record;
	}

	/**
	 * 新しい予約
	 *
	 */
	public function new_booking($daytime=0, $article_id=0) {

		$new = array(
			'booking_id' => 0,
			'booking_time' => $daytime == 0 ? mktime(0, 0, 0, date_i18n('n'), date_i18n('j'), date_i18n('Y')) : $daytime,
			'article_id' => $article_id,
			'user_id' => 0,
			'number' => 0,
			'confirmed' => 0,
			'parent_id' => 0,
			'options' => null,
			'client' => array( //コース追加
				'course' => '',
				'option' => '',
				'option1' => '',
				'option2' => '',
				'option3' => '',
				'option4' => '',
				'option5' => '',
				'option6' => '',
				'option7' => '',
				'option8' => '',
				'option9' => '',
				'company' => '',
				'name' => '',
				'furigana' => '',
				'birthday' => null,
				'gender' => '',
				'email' => '',
				'postcode' => '',
				'address1' => '',
				'address2' => '',
				'tel' => '',
				'adult' => 1,
				'child' => 0,
				'baby' => 0,
				'car' => 0,
			),
			'note' => '',
		);

		return $new;
	}

	/**
	 * 空のオプションデータを戻す
	 *
	 */
	public function new_options() {

		// 初期化されたオプションデータセットを取得する
		return $this->option->optionSet();
	}

	/**
	 * オプションをテーブルに格納する形式に変換する
	 *
	 * @options		array(1=>array(selectable, keyname, name, note), 10=>array(...))
	 * @return		array(keyname=>array(name, number))
	 */
	static public function convert_options($options) {

		$select = array();

		foreach ($options as $option) {
			if (0 < $option['selectable']) {
				$select[$option['keyname']] = array(
					'name' => $option['name'],
					'number' => 0,
				);
			}
		}

		return $select;
	}


	/**
	 * 初期値がセットされた配列にマージする
	 *
	 * @default		初期値がセットされた配列(初期$bookingデータ opitonsがオブジェクト)
	 * $ary			マージする配列(DBから読み込んだデータ optionsが配列)
	 */
	protected function array_merge_default($default=array(), $ary=array()) {

		// 登録データを操作データに変換する
		foreach ($default as $key => $val) {
			if (isset($ary[$key])) {
				// オプションオブジェクトへの変換
				if ($key == 'options') {
					$this->_set_options($val, $ary['options']);
				// 配列データはマージする
				} else if (is_array($default[$key])) {
					$intersect = array_intersect_key($ary[$key], $default[$key]);
					$default[$key] = array_merge($default[$key], $intersect);
				// オブジェクト、配列以外はそのまま
				} else {
					$default[$key] = $ary[$key];
				}
			}
		}

		return $default;
	}

	/**
	 * オプションデータをオプションオブジェクトにセットする
	 *
	 * @optiona		bookingデータのoptions(オブジェクトの配列)
	 * @arrayo		読込んだデータ配列
	 */
	private function _set_options($optiona, $arrayo) {
		$aoptions = array();

		// 1.0初期タイプ
		if (!is_array($arrayo)) {
			return;
		}

		// 1.1より前のデータがあれば1.1構造に変更する
		foreach ($arrayo as $keyname => $val) {
			if (is_array($val)) {
				$aoptions[$val['name']] = $val['number'];
			} else {
				$aoptions[$keyname] = $val;
			}
		}

		// オブジェクトにセットする
		foreach ($optiona as $option) {
			$keyname = $option->getKeyname();
			if (isset($aoptions[$keyname])) {
				$option->setValue($aoptions[$keyname]);
			}
		}

		
	}

	/**
	 * Database table installation
	 *
	 */
	private function _install_table() {
		global $wpdb;

		$option_name = $this->domain . '_table_version';
		$version = get_option($option_name);

		if (empty($version) || $version != self::TABLE_VERSION) {
			require_once(ABSPATH . "wp-admin/includes/upgrade.php");

			// Booking table
			$sql = "CREATE TABLE " . $this->tblBooking . " (
				booking_id int(11) unsigned NOT NULL AUTO_INCREMENT,
				booking_time int(11) unsigned DEFAULT '0',
				confirmed tinyint(1) unsigned DEFAULT '0',
				parent_id int(11) DEFAULT '0',
				article_id int(11) DEFAULT '0',
				user_id int(11) DEFAULT '0',
				number int(10) DEFAULT '0',
				options text,
				client text,
				note text,
				created datetime DEFAULT NULL,
				PRIMARY KEY  (booking_id),
				KEY booking_time (booking_time)) DEFAULT CHARSET=utf8;";
			dbDelta($sql);

			$this->_update_data($version);

			// Update table version
			update_option($option_name, self::TABLE_VERSION);
		}
	}

	/**
	 * その他データのアップデート
	 *
	 */
	private function _update_data($tbl_version) {
		global $wpdb;

		// スケジュールデータをoptionsからpostsへ移動する
		if ($tbl_version == '1.0' && '1.2' < self::VERSION) {
			// 予約品目を読込む
			$articles = MTSSB_Article::get_all_articles();

			// 対象年月のスケジュールデータの読み込み
			$sql = "SELECT *
					FROM {$wpdb->options}
					WHERE option_name REGEXP '{$this->domain}_[[:digit:]]{6}'
					ORDER BY option_name";
			$schedules = $wpdb->get_results($sql, ARRAY_A);

			// スケジュールデータのunserialize
			foreach ($schedules as &$schedule) {
				$schedule['option_value'] = unserialize($schedule['option_value']);
			}

			// 品目毎にスケジュールデータを移動する
			foreach ($articles as $article_id => $article) {
				foreach ($schedules as &$schedule) {
					$aid = 'A' . $article_id;
					// 当該品目のスケジュールデータがあればコピーする
					if (isset($schedule['option_value'][$aid])) {
						$data = $schedule['option_value'][$aid];
						$key_name = MTS_Simple_Booking::SCHEDULE_NAME . substr($schedule['option_name'], 19);
						update_post_meta($article_id, $key_name, $data);
					}
				}
			}

			// optionsのスケジュールデータを削除する
			//foreach ($schedules as &$schedule) {
			//	delete_option($schedule['option_name']);
			//}
		}
	}

	/**
	 * 予約オブジェクトデータの参照を戻す
	 *
	 */
	public function getBooking() {
		return $this->booking;
	}

	/**
	 * 入力年齢制限
	 *
	 */
	protected function _age_limit() {
		return apply_filters('mtssb_booking_age_limit', array(
			'lower' => 1,
			'upper' => 90,
		));
	}
}


/**
 * MTS 日時アクセスモジュール
 *
 * @Date		2012-12-04
 * @Author		S.Hayashi
 *
 */
class MTS_WPDate {

	private		$utime = 0;
	private		$adate = array('year' => 0, 'month' => 0, 'day' => 0);

	public function __construct() {

	}

	/**
	 * Unix Timeをセットしてオブジェクトを戻す
	 *
	 * $utm
	 */
	public function set_time($utm) {
		$this->utime = $utm;

		return $this;
	}

	/**
	 * 日付がセットされているか確認する
	 *
	 */
	public function isSetDate() {
		if ($this->adate['year'] == 0) {
			return false;
		}
		return true;
	}

	/**
	 * 日付文字列をセットする
	 *
	 * @dstr	'Y-n-j'
	 */
	public function set_date($dstr) {
		$dd = split('-', $dstr);
		if (count($dd) < 3) {
			return false;
		}

		if (!checkdate($dd[1], $dd[2], $dd[0])) {
			return false;
		}

		$this->adate['year'] = intval($dd[0]);
		$this->adate['month'] = intval($dd[1]);
		$this->adate['day'] = intval($dd[2]);

		return true;
	}

	/**
	 * 配列日付をセットする
	 *
	 * @ainp	array('year', 'month', 'day')
	 */
	public function normalize($ainp) {
		return $this->set_date(implode('-', $ainp));
	}

	/**
	 * 日付を区切子付きで戻す
	 *
	 * @sep		'-' or 'j'
	 */
	public function get_date($sep='-') {
		if ($this->adate['year'] == 0) {
			return '';
		}

		if ($sep == 'j') {
			return $this->adate['year'] . '年' . $this->adate['month'] . '月' . $this->adate['day'] . '日';
		}

		return $this->adate['year'] . $sep . $this->adate['month'] . $sep . $this->adate['day'];
	}

	/**
	 * 年を戻す
	 */
	public function get_year() {
		return $this->adate['year'];
	}

	/**
	 * 月を戻す
	 */
	public function get_month() {
		return $this->adate['month'];
	}

	/**
	 * 日を戻す
	 */
	public function get_day() {
		return $this->adate['day'];
	}

	/**
	 * 年月日入力フォーム出力
	 *
	 * @keyname		id名
	 * @name		name名
	 * @yearf		カレント年からの未来年
	 * @yearb		カレント年からの過去年
	 * @space		true or false(セレクト上段に空白有無)
	 */
	public function date_form($keyname, $name, $yearf=1, $yearb=1, $space=false) {

		if ($this->utime != 0) {
			$year = date_i18n('Y', $this->utime);
			$month = date_i18n('n', $this->utime);
			$day = date_i18n('j', $this->utime);
		} else {
			extract($this->adate);
		}

		$today = split('-', date_i18n('Y-n-j'));

		ob_start();
?>
		<span class="date-form">
			<select id="<?php echo $keyname ?>_year" class="booking-date" name="<?php echo $name ?>[year]">
				<?php if ($space) : ?><option value="0"<?php echo $year == 0 ? ' selected="selected"' : '' ?>>&nbsp;</option><?php endif; ?>
				<?php for ($yy = $today[0] + $yearf; $today[0] - $yearb < $yy; $yy--) : ?><option value="<?php echo $yy ?>"<?php echo $yy == $year ? ' selected="selected"' : '' ?>><?php echo $yy ?></option><?php endfor; ?>
			</select>年
		</span>
		<span class="date-from">
			<select id="<?php echo $keyname ?>_month" class="booking-date" name="<?php echo $name ?>[month]">
				<?php if ($space) : ?><option value="0"<?php echo $month == 0 ? ' selected="selected"' : '' ?>>&nbsp;</option><?php endif; ?>
				<?php for ($mm = 1; $mm <= 12; $mm++) : ?><option value="<?php echo $mm ?>"<?php echo $month == $mm ? ' selected="selected"' : '' ?>><?php echo $mm ?></option><?php endfor; ?>
			</select>月
		</span>
		<span class="date-form">
			<select id="<?php echo $keyname ?>_day" class="booking-date" name="<?php echo $name ?>[day]">
				<?php if ($space) : ?><option value="0"<?php echo $day == 0 ? ' selected="selected"' : '' ?>>&nbsp;</option><?php endif; ?>
				<?php for ($dd = 1; $dd <= 31; $dd++) : ?><option value="<?php echo $dd ?>"<?php echo $day == $dd ? ' selected="selected"' : '' ?>><?php echo $dd ?></option><?php endfor; ?>
			</select>日
		</span>

<?php
		return ob_get_clean();
	}

	/**
	 * 年月日入力hiddenフォーム出力
	 *
	 * @name		name名
	 */
	public function date_form_hidden($name) {
		if ($this->utime != 0) {
			$year = date_i18n('Y', $this->utime);
			$month = date_i18n('n', $this->utime);
			$day = date_i18n('j', $this->utime);
		} else {
			extract($this->adate);
		}

		ob_start();
?>
		<input type="hidden" name="<?php echo $name ?>[year]" value="<?php echo $year ?>" />
		<input type="hidden" name="<?php echo $name ?>[month]" value="<?php echo $month ?>" />
		<input type="hidden" name="<?php echo $name ?>[day]" value="<?php echo $day ?>" />

<?php
		return ob_get_clean();
	}
}
