<?php
if (!class_exists('MTSSB_Booking')) {
	require_once(dirname(__FILE__) . '/mtssb-booking.php');
}
/**
 * MTS Simple Booking 予約ページ処理モジュール
 *
 * @Filename	mtssb-booking-form.php
 * @Date		2012-05-15
 * @Author		S.Hayashi
 *
 * Updated to 1.2.0 on 2012-12-22
 * Updated to 1.1.5 on 2012-12-02
 * Updated to 1.1.0 on 2012-11-04
 */

class MTSSB_Booking_Form extends MTSSB_Booking {
	const VERSION = '1.2.0';
	const PAGE_NAME = 'booking-form';

	// 予約条件パラメータ
	public $controls;

	// 顧客データのカラム情報
	private $clcols;

	// 予約データ
	private $reserved;

	// 予約日時に関する情報
	private $thetime;
	private $daytime;
	private $this_year;		// 現在年
	private $this_month;	// 現在月
	private $this_day;		// 現在日
	private $today_time;	// 現在年月日 Unix Time
	private $start_time;
	private $max_time;

	// 予約品目
	private $article_id;
	public $article;

	// 当該日スケジュール(array('open','delta','class'));
	private $schedule;

	// 入力フォームメッセージ
	private $message;

	/**
	 * Error
	 */
	private $err_message = '';
	private $errmsg = array();

	/**
	 * Constructor
	 *
	 */
	public function __construct() {

		parent::__construct();

		// 予約条件パラメータのロード
		$this->controls = get_option($this->domain . '_controls');

		// 表示ページのURL
		$this->this_page = get_permalink();

		// 時間情報の取得
		$this->this_year = date_i18n('Y');
		$this->this_month = date_i18n('n');
		$this->this_day = date_i18n('j');
		$this->today_time = mktime(0, 0, 0, $this->this_month, $this->this_day, $this->this_year);
		$this->start_time = $this->today_time + intval($this->controls['start_accepting']) * 60;
		$this->max_time = mktime(0, 0, 0, $this->this_month + $this->controls['period'], $this->this_day, $this->this_year);

		// 顧客データのカラム利用設定情報を読込む
		$reserve = get_option($this->domain . '_reserve');
		$this->clcols = $reserve['column'];
	}

	/**
	 * フォーム入力予約登録処理
	 *
	 */
	public function front_booking() {

		// NONCEチェック
		if (!wp_verify_nonce($_POST['nonce'], "{$this->domain}_" . self::PAGE_NAME)) {
			$this->err_message = $this->_err_message('NONCE_ERROR');
			return false;
		}

		// 予約品目、予約時間の事前チェック
		if (!$this->_pre_check()) {
			return false;
		}

		// 予約データを正規化し、登録データを取得する
		$this->booking = $this->normalize_booking($_POST['booking'], $this->article['count']);

		// 入力チェック
		$check_mail2 = false;
		if (!$this->_input_validation($check_mail2)) {
			$this->err_message = $this->_err_message('ERROR_BEFORE_ADDING');
			return false;
		}

		// 予約を新規登録する
		$booking_id = $this->add_booking();
		if (!$booking_id) {
			$this->err_message = $this->_err_message('ERROR_ADD_BOOKING');
			return false;
		}

		$this->booking['booking_id'] = $booking_id;
		return $booking_id;
	}

	/**
	 * メールの送信エラーメッセージをセット
	 *
	 */
	public function error_send_mail() {
		$this->err_message = $this->_err_message('ERROR_SEND_MAIL');
	}

	/**
	 * ステータス別予約フォーム処理
	 *
	 */
	public function booking_form($content) {

		// 予約登録処理実行の後処理
		$action = isset($_POST['action']) ? $_POST['action'] : '';
		if ($action == 'confirm') {
			if (empty($this->err_message)) {
				return $this->_out_completed();
			}
			return $this->_out_errorbox();
		}

		// 予約品目、予約時間の事前チェック
		if (!$this->_pre_check()) {
			return $this->_out_errorbox();
		}

		// SUBMIT処理
		if (isset($_POST['action']) && $action == 'validate') {

			// NONCEチェック
			if (!wp_verify_nonce($_POST['nonce'], "{$this->domain}_" . self::PAGE_NAME)) {
				$this->err_message = $this->_err_message('NONCE_ERROR');
				return $this->_out_errorbox();
			}

			// 予約データを正規化し、登録データを取得する
			$this->booking = $this->normalize_booking($_POST['booking'], $this->article['count']);

			// 入力チェック
			if ($this->_input_validation()) {
				return $content . $this->_confirming_form();
			}

		// 入力がなければ初期化
		} else {
			$this->booking = $this->new_booking();
			$this->booking['booking_time'] = $this->thetime;
			$this->booking['article_id'] = $this->article_id;
		}

		return $content . $this->_input_form();
	}

	/**
	 * 予約処理の共通となる事前チェック
	 *
	 */
	private function _pre_check() {

		// 予約日時
		if (isset($_POST['booking']['booking_time'])) {
			$this->thetime = intval($_POST['booking']['booking_time']);
		} else {
			$this->thetime = isset($_REQUEST['utm']) ? intval($_REQUEST['utm']) : 0;
		}

		// 予約品目の取得
		if (isset($_POST['booking']['article_id'])) {
			$this->article_id = intval($_POST['booking']['article_id']);
		} else {
			$this->article_id = isset($_REQUEST['aid']) ? intval($_REQUEST['aid']) : 0;
		}

		// 予約受付の確認
		if (!$this->_booking_acceptance()) {
			return false;
		}

		// 予約の空き確認
		if (!$this->_booking_vacancy()) {
			return false;
		}

		return true;
	}

	/**
	 * 予約受付の日時、対象品目の確認
	 *
	 */
	protected function _booking_acceptance() {

		// 予約受付中か確認
		if ($this->controls['available'] != 1) {
			$this->err_message = $this->_err_message('UNAVAILABLE');
			return false;
		}

		// 予約受付期間内か確認
		if ($this->thetime < $this->start_time || $this->max_time <= $this->thetime) {
			$this->err_message = $this->_err_message('OUT_OF_PERIOD');
			return false;
		}

		// 予約スケジュールデータを取得する
		$key_name = MTS_Simple_Booking::SCHEDULE_NAME . date_i18n('Ym', $this->thetime);
		$schedule = get_post_meta($this->article_id, $key_name, true);

		// スケジュールが登録されており予約を受け付けているか確認する
		$day = date_i18n('d', $this->thetime);
		if (!empty($schedule[$day])) {
			$this->schedule = $schedule[$day];
			if ($this->schedule['open'] != 1) {
				$this->err_message = $this->_err_message('UNACCEPTABLE_DAY');
				return false;
			}
		} else {
			// スケジュールが登録されていない場合
			$this->err_message = $this->_err_message('UNAVAILABLE');
			return false;
		}

		// 予約品目データを取得する
		$this->article = MTSSB_Article::get_the_article($this->article_id);

		// 予約時間の確認
		if (!in_array($this->thetime % 86400, $this->article['timetable'])) {
			$this->err_message = $this->_err_message('UNACCEPTABLE_TIME');
			return false;
		}

		// オプション有無の処理セット(オプション利用指定かつオプションデータ有)
		if ($this->article['addition'] && !empty($this->option)) {
			$this->option_available = true;
		}

		return true;
	}

	/**
	 * 予約の空き確認
	 *
	 */
	protected function _booking_vacancy() {

		// 予約済みデータを読込む
		$this->reserved = $this->get_reserved_day_count($this->thetime - $this->thetime % 86400);

		// 予約データがあれば予約リミット内か確認する
		if (isset($this->reserved[$this->thetime][$this->article_id])) {
			$reserved = &$this->reserved[$this->thetime][$this->article_id];
			$filled = false;
			if ($this->article['restriction'] == 'capacity') {
				if ($this->article['capacity'] + intval($this->schedule['delta']) <= $reserved['number']) {
					$filled = true;
				}
			} else {
				if ($this->article['quantity'] + intval($this->schedule['delta']) <= $reserved['count']) {
					$filled = true;
				}
			}
			if ($filled) {
				$this->err_message = $this->_err_message('CLOSED_BOOKING');
				return false;
			}
		}

		return true;
	}

	/**
	 * 入力の正規化と確認
	 *
	 */
	protected function _input_validation($check_mail2=true) {

		$this->errmsg = array();

		// 入退場入力があれば時刻データを一時保管する
		if ($this->controls['message']['temps_utile'] && isset($_POST['temps_utile'])) {
			$this->message['temps_utile'] = $_POST['temps_utile'];
		}

		// 入場人数の確認
		if ($this->booking['number'] < $this->article['minimum'] || $this->article['maximum'] < $this->booking['number']) {
			$this->errmsg['count'] = $this->_err_message('INVALID_NUMBER');
		}

		// 予約上限を人数で制限している場合のチェック
		if ($this->article['restriction'] == 'capacity') {
			$limit = $this->article['capacity'] + $this->schedule['delta'];
			if (isset($this->reserved[$this->booking['booking_time']][$this->booking['article_id']])) {
				$limit -= $this->reserved[$this->booking['booking_time']][$this->booking['article_id']]['number'];
			}
			if ($limit < $this->booking['number']) {
				$this->errmsg['count'] = $this->_err_message('DEFICIENT_PLACE');
			}
		}

		// 必須入力連絡先項目の確認
		foreach ($this->clcols as $key => $val) {
			$chkkey = $key == 'address' ? 'address1' : $key;
			if ($val == 1 && empty($this->booking['client'][$chkkey])) {
				$this->errmsg[$chkkey] = $this->_err_message('REQUIRED');
			}
		}

		// 年齢制限の確認
		if (isset($this->clcols['birthday']) && 1 == $this->clcols['birthday']) {
			$limit = $this->_age_limit();
			$age = $this->this_year - $this->booking['client']['birthday']->get_year();
			if ($age < $limit['lower'] || $limit['upper'] < $age) {
				$this->errmsg['birthday'] = $this->_err_message('INVALID_AGE');
			}
		}

		// E-Mailの確認
		if (0 < $this->clcols['email'] && !empty($this->booking['client']['email'])) {
			if (!preg_match("/^[0-9a-z_\.\-]+@[0-9a-z_\-\.]+$/i", $this->booking['client']['email'])) {
				$this->errmsg['email'] = $this->_err_message('INVALID_EMAIL');
			} else if ($this->clcols['email'] == 1 && $check_mail2
			 && $this->booking['client']['email'] != $_POST['booking']['client']['email2']) {
				$this->errmsg['email'] = $this->_err_message('UNMATCH_EMAIL');
			}
		}

		// 郵便番号の確認
		if (0 < $this->clcols['postcode']) {
			if (!preg_match("/^[0-9\-]*$/", $this->booking['client']['postcode'])) {
				$this->errmsg['postcode'] = $this->_err_message('NOT_NUMERIC');
			}
		}

		// 電話番号の確認
		if (0 < $this->clcols['tel']) {
			if (!preg_match("/^[0-9_\-\(\)]*$/", $this->booking['client']['tel'])) {
				$this->errmsg['tel'] = $this->_err_message('NOT_NUMERIC');
			}
		}

		if (!empty($this->errmsg)) {
			return false;
		}

		// メッセージ入力に追加を付加する
		if ($this->controls['message']['temps_utile'] && isset($_POST['temps_utile'])) {
			$temps_utile = sprintf("入場予定 %02d:%02d　退場予定 %02d:%02d", intval($this->message['temps_utile']['in']['hour']),
			 intval($this->message['temps_utile']['in']['minute']), intval($this->message['temps_utile']['out']['hour']), intval($this->message['temps_utile']['out']['minute']));
			$this->booking['note'] = $temps_utile . "\n\n{$this->booking['note']}"; //(empty($this->record['note']) ? '' : ("\n\n" . $this->record['note']));
		}

		return true;
	}

	/**
	 * エラーメッセージ
	 *
	 */
	protected function _err_message($err_name) {
		switch ($err_name) {
			case 'UNAVAILABLE':
				return 'ただ今予約は受け付けておりません。';
			case 'OUT_OF_PERIOD':
				return '予約受付期間外です。';
			case 'UNACCEPTABLE_DAY':
				return '指定日は予約を受け付けておりません。';
			case 'UNACCEPTABLE_TIME':
				return '指定時間は予約を受け付けておりません。';

			case 'CLOSED_BOOKING':
				return '指定日時の予約受け付けは終了しました。';

			case 'NONCE_ERROR':
				return 'Nonce Check Fault.';
			case 'INVALID_NUMBER':
				return '予約の人数が受付範囲外です。';
			case 'DEFICIENT_PLACE':
				return '指定された人数分の予約はできません。';
			case 'REQUIRED':
				return 'この項目は必ず入力して下さい。';
			case 'INVALID_AGE':
				return '生年月日の入力が正しくありません。';
			case 'INVALID_EMAIL':
				return 'メールアドレスの指定が正しくありません。';
			case 'UNMATCH_EMAIL':
				return 'メールアドレスが確認用と一致しませんでした。';
			case 'NOT_NUMERIC':
				return '数字以外の文字が見つかりました。';

			case 'ERROR_BEFORE_ADDING':
				return '入力チェックエラーが登録前に見つかりました。';
			case 'ERROR_ADD_BOOKING':
				return '予約のデータ登録を失敗しました。';
			case 'ERROR_SEND_MAIL':
				return 'メールの送信を失敗しました。電話で予約の確認をお願いします。';

			default :
				return '入力エラーです。';
		}
	}


	/**
	 * エラーエレメントの出力
	 *
	 */
	protected function _out_errorbox() {
		ob_start();
?>
		<div class="error-message error-box">
			<?php echo $this->err_message ?>
		</div>
<?php
		return ob_get_clean();
	}

	/**
	 * 予約完了エレメントの出力
	 *
	 */
	protected function _out_completed() {
		ob_start();
?>
		<div class="info-message booking-completed">
			ご予約ありがとうございました。
		</div>
<?php
		return ob_get_clean();
	}

	/**
	 * お客様入力フォームの表示
	 *
	 */
	protected function _input_form() {
		global $current_user;

		$url = get_permalink();
		$client = $this->booking['client'];

		// 初期値
		$email2 = '';

		// 年齢制限データの取得
		$agelimit = $this->_age_limit();

		// ログイン中であればログインユーザー情報をセットする
		if (is_user_logged_in() && empty($client['name']) && empty($client['email'])) {
			get_currentuserinfo();
			$client['company'] = get_the_author_meta('mtscu_company', $current_user->ID);
			$client['name'] = $current_user->last_name . ' ' . $current_user->first_name;
			$client['furigana'] = get_the_author_meta('mtscu_furigana', $current_user->ID);
			$client['email'] = $email2 = $current_user->user_email;
			$client['postcode'] = get_the_author_meta('mtscu_postcode', $current_user->ID);
			$client['address1'] = get_the_author_meta('mtscu_address1', $current_user->ID);
			$client['address2'] = get_the_author_meta('mtscu_address2', $current_user->ID);
			$client['tel'] = get_the_author_meta('mtscu_tel', $current_user->ID);
			$this->booking['user_id'] = $current_user->ID;
		}

		ob_start();
?>

<div id="booking-form" class="content-form">

<form method="post" action="<?php echo $url ?>">
	<fieldset id="booking-reservation-fieldset">
	<legend><?php echo apply_filters('booking_form_number_title', 'ご予約') ?></legend>
	<?php echo apply_filters('booking_form_number_message', '') ?>
	<table>
		<tr>
			<th>予約</th>
			<td><?php echo esc_html($this->article['name']) ?><br />
				<?php echo apply_filters('booking_form_date', date('Y年n月j日 H:i', $this->thetime), $this->thetime) ?>
			</td>
		</tr>
		
<?php if($this->article['name']=="記事タイトルの名前"){//条件つけれた！記事タイトルの名前でおｋ ?>
<?php if (0 < $this->clcols['course']) : ?><tr>
	<th><label for="client-course"><?php echo apply_filters('booking_form_course', 'メニュー'); echo $this->clcols['course'] == 1 ? $this->_require_message() : '' ?></label></th>
	<td>
	<select name="booking[client][course]">
		<option>メニューA</option>
	</select>
</tr><?php endif; ?>
<? } ?>	

<?php if($this->article['name']=="記事タイトルの名前2"){ ?>
<?php if (0 < $this->clcols['course']) : ?><tr>
	<th><label for="client-course"><?php echo apply_filters('booking_form_course', 'メニュー'); echo $this->clcols['course'] == 1 ? $this->_require_message() : '' ?></label></th>
	<td>
	<select name="booking[client][course]">
		<option>メニューA</option>
		<option>メニューB</option>
	</select>
</tr><?php endif; ?>
<? } ?>	
<?php if($this->article['name']=="記事タイトルの名前3"){ ?>
<?php if (0 < $this->clcols['course']) : ?><tr>
	<th><label for="client-course"><?php echo apply_filters('booking_form_course', 'メニュー'); echo $this->clcols['course'] == 1 ? $this->_require_message() : '' ?></label></th>
	<td>
	<select name="booking[client][course]">
		<option>メニューA</option>
		<option>メニューB</option>
		<option>メニューC</option>
	</select>
</tr><?php endif; ?>
<? } ?>	
<?php if($this->article['name']=="記事タイトルの名前4"){ ?>
<?php if (0 < $this->clcols['course']) : ?><tr>
	<th><label for="client-course"><?php echo apply_filters('booking_form_course', 'メニュー'); echo $this->clcols['course'] == 1 ? $this->_require_message() : '' ?></label></th>
	<td>
	<select name="booking[client][course]">
		<option>メニューA</option>
		<option>メニューD</option>
		<option>メニューE</option>
	</select>
</tr><?php endif; ?>
<? } ?>	

<tr>
	<th><label for="client-course"><?php echo apply_filters('booking_form_option', 'オプション'); echo $this->clcols['option'] == 1 ? $this->_require_message() : '' ?></label></th>
	<td>
<ul>
	<li><input type="checkbox" name="booking[client][option1]" value="オプション1" />オプション1</li>
	<li><input type="checkbox" name="booking[client][option2]" value="オプション2" />オプション2</li>
	<li><input type="checkbox" name="booking[client][option3]" value="オプション3" />オプション3</li>
	<li><input type="checkbox" name="booking[client][option4]" value="オプション4" />オプション4</li>
	<li><input type="checkbox" name="booking[client][option5]" value="オプション5" />オプション5</li>
	<li><input type="checkbox" name="booking[client][option6]" value="オプション6" />オプション6</li>
	<li><input type="checkbox" name="booking[client][option7]" value="オプション7" />オプション7</li>
	<li><input type="checkbox" name="booking[client][option8]" value="オプション8" />オプション8</li>
	<li><input type="checkbox" name="booking[client][option9]" value="オプション9" />オプション9</li>
</ul></td>
</tr>

		<tr>
			<th><label for="client-adult">人数</label></th>
			<td>
				<?php foreach ($this->controls['count'] as $key => $val) : ?><div class="input-number"<?php echo $val != 1 ? ' style="display:none"' : '' ?>><?php
					$title = apply_filters('booking_form_count_label', __(ucwords($key), $this->domain));
				 	if ($title != '') { echo "<label for=\"client-{$key}\">$title</label><br />"; }
				?>
					<input id="client-<?php echo $key ?>" type="text" class="content-text small right" name="booking[client][<?php echo $key ?>]" value="<?php echo esc_html($client[$key]) ?>" maxlength="5" /> 
				</div><?php endforeach; ?>
				<?php if (isset($this->errmsg['count'])) : ?><div class="error-message"><?php echo $this->errmsg['count'] ?></div><?php endif; ?>
			</td>
		</tr>
	</table>
	</fieldset>

	<fieldset id="booking_client-fieldset">
	<legend><?php echo apply_filters('booking_form_client_title', 'ご連絡先') ?></legend>
	<?php echo apply_filters('booking_form_client_message', '<span class="required">※</span>の項目は必須です。') ?>

	<table>
		<?php if (0 < $this->clcols['company']) : ?><tr>
			<th><label for="client-company"><?php echo apply_filters('booking_form_company', '会社名'); echo $this->clcols['company'] == 1 ? $this->_require_message() : '' ?></label></th>
			<td>
				<input id="client-company" class="content-text medium" type="text" name="booking[client][company]" value="<?php echo esc_html($client['company']) ?>" maxlength="100" />
			<?php if (isset($this->errmsg['company'])) : ?><div class="error-message"><?php echo $this->errmsg['company'] ?></div><?php endif; ?></td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['name']) : ?><tr>
			<th><label for="client-name"><?php echo apply_filters('booking_form_name', 'お名前'); echo $this->clcols['name'] == 1 ? $this->_require_message() : '' ?></label></th>
			<td>
				<input id="client-name" class="content-text medium" type="text" name="booking[client][name]" value="<?php echo esc_html($client['name']) ?>" maxlength="100" />
			<?php if (isset($this->errmsg['name'])) : ?><div class="error-message"><?php echo $this->errmsg['name'] ?></div><?php endif; ?></td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['furigana']) : ?><tr>
			<th><label for="client-furigana"><?php echo apply_filters('booking_form_furigana', 'フリガナ'); echo $this->clcols['furigana'] == 1 ? $this->_require_message() : '' ?></label></th>
			<td>
				<input id="client-furigana" class="content-text medium" type="text" name="booking[client][furigana]" value="<?php echo esc_html($client['furigana']) ?>" maxlength="100" />
			<?php if (isset($this->errmsg['furigana'])) : ?><div class="error-message"><?php echo $this->errmsg['furigana'] ?></div><?php endif; ?></td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['email']) : ?><tr>
			<th><label for="client-email"><?php echo apply_filters('booking_form_email', 'E-Mail'); echo $this->clcols['email'] == 1 ? $this->_require_message() : '' ?></label></th>
			<td>
				<input id="client-email" class="content-text fat" type="text" name="booking[client][email]" value="<?php echo esc_html($client['email']) ?>" maxlength="100" />
			<?php if (isset($this->errmsg['email'])) : ?><div class="error-message"><?php echo $this->errmsg['email'] ?></div><?php endif; ?></td>
		</tr>
		<?php if ($this->clcols['email'] == 1) : ?><tr>
			<th><label for="client-email2"><?php echo apply_filters('booking_form_email2', 'E-Mail(確認用)') ?></label></th>
			<td>
				<input id="client-email2" class="content-text fat" type="text" name="booking[client][email2]" value="<?php echo $email2 ?>" maxlength="100" />
			</td>
		</tr><?php endif; endif; ?>
		<?php if (0 < $this->clcols['postcode']) : ?><tr>
			<th><label for="client-postcode"><?php echo apply_filters('booking_form_postcode', '年齢'); echo $this->clcols['postcode'] == 1 ? $this->_require_message() : '' ?></label></th>
			<td>
				<input id="client-postcode" class="content-text medium" type="text" name="booking[client][postcode]" value="<?php echo esc_html($client['postcode']) ?>" maxlength="10" />
			<?php if (isset($this->errmsg['postcode'])) : ?><div class="error-message"><?php echo $this->errmsg['postcode'] ?></div><?php endif; ?></td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['address']) : ?><tr>
			<th><label for="client-address1"><?php echo apply_filters('booking_form_address', '住所'); echo $this->clcols['address'] == 1 ? $this->_require_message() : '' ?></label></th>
			<td>
				<input id="client-address1" class="content-text fat" type="text" name="booking[client][address1]" value="<?php echo esc_html($client['address1']) ?>" maxlength="100" /><br />
				<input id="client-address2" class="content-text fat" type="text" name="booking[client][address2]" value="<?php echo esc_html($client['address2']) ?>" maxlength="100" />
			<?php if (isset($this->errmsg['address'])) : ?><div class="error-message"><?php echo $this->errmsg['address'] ?></div><?php endif; ?></td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['tel']) : ?><tr>
			<th><label for="client-tel"><?php echo apply_filters('booking_form_tel', '電話番号'); echo $this->clcols['tel'] == 1 ? $this->_require_message() : '' ?></label></th>
			<td>
				<input id="client-tel" class="content-text medium" type="text" name="booking[client][tel]" value="<?php echo esc_html($client['tel']) ?>" maxlength="20" />
			<?php if (isset($this->errmsg['tel'])) : ?><div class="error-message"><?php echo $this->errmsg['tel'] ?></div><?php endif; ?></td>
		</tr><?php endif; ?>
	</fieldset>

	<fieldset id="booking-message-fieldset">
	<legend><?php echo apply_filters('booking_form_message_title', 'ご連絡事項') ?></legend>
	<?php echo apply_filters('booking_form_message_message', '') ?>
		<?php if ($this->controls['message']['temps_utile']) :
				$intime = $outtime = $this->thetime;
				if (isset($this->message['temps_utile'])) {
					$intime = mktime($this->message['temps_utile']['in']['hour'], $this->message['temps_utile']['in']['minute'], 0, 0, 0, 0);
					$outtime = mktime($this->message['temps_utile']['out']['hour'], $this->message['temps_utile']['out']['minute'], 0, 0, 0, 0);
				} ?><tr>
			<th><label for="message-temps_utile"><?php echo apply_filters('booking_form_message_inout_title', '入退場予定') ?></label></th>
			<td>
				<?php echo apply_filters('booking_form_message_intime', '入場 ') ?><?php echo $this->_time_select('temps_utile[in]', $intime) ?><br />
				<?php echo apply_filters('booking_form_message_outtime', '退場 ') ?><?php echo $this->_time_select('temps_utile[out]', $outtime) ?>
			</td>
		</tr><?php endif; ?>
		<tr>
			<th><label for="booking-note"><?php echo apply_filters('booking_form_message_header', 'その他') ?></label></th>
			<td>
				<textarea id="booking-note" class="content-text fat" name="booking[note]" rows="5" cols="200"><?php echo esc_textarea($this->booking['note']) ?></textarea>
			</td>
		</tr>
	</fieldset>
	</table>

	<div id="action-button" style="text-align: center">
		<?php echo apply_filters('booking_form_send_button', '<button type="submit" name="reserve_action" value="validate">予約確認</button>'); ?>
	</div>
	<input type="hidden" name="nonce" value="<?php echo wp_create_nonce("{$this->domain}_" . self::PAGE_NAME) ?>" />
	<input type="hidden" name="action" value="validate" />
	<input type="hidden" name="booking[article_id]" value="<?php echo $this->article_id ?>" />
	<input type="hidden" name="booking[booking_time]" value="<?php echo $this->thetime ?>" />
	<input type="hidden" name="booking[user_id]" value="<?php echo $this->booking['user_id'] ?>" />
</form>
</div>
<?php
		return ob_get_clean();

	}

	/**
	 * 必須入力項目マーク表示
	 *
	 */
	private function _require_message() {
		return '(<span class="required">※</span>)';
	}

	/**
	 * 時分選択セレクトの表示
	 *
	 */
	private function _time_select($name, $time) {
		$hour = date('H', $time);
		$minute = date('i', $time);

		ob_start();
?>
		<select name="<?php echo $name . '[hour]' ?>">
			<?php for ($i = 0; $i < 23; $i++) : ?><option value="<?php echo $i ?>"<?php echo $i == $hour ? ' selected="selected"' : '' ?>><?php echo sprintf('%02d', $i) ?></option><?php endfor; ?>
		</select> 時 
		<select name="<?php echo $name . '[minute]' ?>">
			<?php for ($i = 0; $i < 60; $i++) : ?><option value="<?php echo $i ?>"<?php echo $i == $minute ? ' selected="selected"' : '' ?>><?php echo sprintf('%02d', $i) ?></option><?php endfor; ?>
		</select> 分

<?php
		return ob_get_clean();
	} 


	/**
	 * 予約入力確認フォームの表示
	 *
	 */
	protected function _confirming_form() {

		$url = get_permalink();
		$client = $this->booking['client'];

		// オプション追加選択の処理
		if ($this->article['addition'] == 1) {
			$options = get_option($this->domain . '_option');
		}

		ob_start();
?>

<div id="booking-form" class="content-form">

<form method="post" action="<?php echo $url ?>">
	<fieldset id="booking-confirm-fieldset">
	<legend><?php echo apply_filters('booking_form_confirm_title', '入力の確認') ?></legend>
	<table>
		<tr>
			<th>予約</th>
			<td><?php echo esc_html($this->article['name']) ?><br />
				<?php echo apply_filters('booking_form_date', date('Y年n月j日 H:i', $this->booking['booking_time']), $this->booking['booking_time']) ?>
			</td>
		</tr>

<?php if (0 < $this->clcols['course']) : ?><tr>
    <th><?php echo apply_filters('booking_form_course', 'メニュー') ?></th>
    <td>
        <?php echo esc_html($client['course']) ?>
        <input type="hidden" name="booking[client][course]" value="<?php echo esc_html($client['course']) ?>" />
    </td>
</tr><?php endif; ?>

<?php if (0 < $this->clcols['option']) : ?><tr>
    <th><?php echo apply_filters('booking_form_option', 'オプション') ?></th>
    <td>
        <ul>
		
<?php if ($client['option1'] != NULL ) : ?>
		<li><?php echo esc_html($client['option1']) ?>
        <input type="hidden" name="booking[client][option1]" value="<?php echo esc_html($client['option1']) ?>" /></li>
<?php endif; ?>
<?php if ($client['option2'] != NULL ) : ?>
		<li><?php echo esc_html($client['option2']) ?>
        <input type="hidden" name="booking[client][option2]" value="<?php echo esc_html($client['option2']) ?>" /></li>
<?php endif; ?>
<?php if ($client['option3'] != NULL ) : ?>
		<li><?php echo esc_html($client['option3']) ?>
        <input type="hidden" name="booking[client][option3]" value="<?php echo esc_html($client['option3']) ?>" /></li>
<?php endif; ?>
<?php if ($client['option4'] != NULL ) : ?>
        <li><?php echo esc_html($client['option4']) ?>
        <input type="hidden" name="booking[client][option4]" value="<?php echo esc_html($client['option4']) ?>" /></li>
<?php endif; ?>
<?php if ($client['option5'] != NULL ) : ?>
        <li><?php echo esc_html($client['option5']) ?>
        <input type="hidden" name="booking[client][option5]" value="<?php echo esc_html($client['option5']) ?>" /></li>
<?php endif; ?>
<?php if ($client['option6'] != NULL ) : ?>
        <li><?php echo esc_html($client['option6']) ?>
        <input type="hidden" name="booking[client][option6]" value="<?php echo esc_html($client['option6']) ?>" /></li>
<?php endif; ?>
<?php if ($client['option7'] != NULL ) : ?>
        <li><?php echo esc_html($client['option7']) ?>
        <input type="hidden" name="booking[client][option7]" value="<?php echo esc_html($client['option7']) ?>" /></li>
<?php endif; ?>
<?php if ($client['option8'] != NULL ) : ?>
        <li><?php echo esc_html($client['option8']) ?>
        <input type="hidden" name="booking[client][option8]" value="<?php echo esc_html($client['option8']) ?>" /></li>
<?php endif; ?>
<?php if ($client['option9'] != NULL ) : ?>
        <li><?php echo esc_html($client['option9']) ?>
        <input type="hidden" name="booking[client][option8]" value="<?php echo esc_html($client['option9']) ?>" /></li>
<?php endif; ?>
		</ul>
    </td>
</tr><?php endif; ?>

		<tr>
			<th>予約人数</th>
			<td>
				<?php foreach ($this->controls['count'] as $key => $val) : ?><div class="input-number"<?php echo $val != 1 ? ' style="display:none"' : '' ?>><?php
					$title = apply_filters('booking_form_count_label', __(ucwords($key), $this->domain));
				 	if ($title != '') { echo "$title<br />"; }
				?>
					<?php echo esc_html($client[$key]) ?><input type="hidden" name="booking[client][<?php echo $key ?>]" value="<?php echo esc_html($client[$key]) ?>" maxlength="5" /><?php echo apply_filters('booking_form_count_note', '') ?>
				</div><?php endforeach; ?>
				<?php if (isset($this->errmsg['count'])) : ?><div class="error-message"><?php echo $this->errmsg['count'] ?></div><?php endif; ?>
			</td>
		</tr>

		<tr>
			<td class="option-confirm-header" colspan="2"><?php echo apply_filters('booking_form_client_title', 'ご連絡先') ?></td>
		</tr>
		<?php if (0 < $this->clcols['company']) : ?><tr>
			<th><?php echo apply_filters('booking_form_company', '会社名') ?></th>
			<td>
				<?php echo esc_html($client['company']) ?>
				<input type="hidden" name="booking[client][company]" value="<?php echo esc_html($client['company']) ?>" />
			</td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['name']) : ?><tr>
			<th><?php echo apply_filters('booking_form_name', 'お名前') ?></th>
			<td>
				<?php echo esc_html($client['name']) ?>
				<input type="hidden" name="booking[client][name]" value="<?php echo esc_html($client['name']) ?>" />
			</td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['furigana']) : ?><tr>
			<th><?php echo apply_filters('booking_form_furigana', 'フリガナ') ?></th>
			<td>
				<?php echo esc_html($client['furigana']) ?>
				<input type="hidden" name="booking[client][furigana]" value="<?php echo esc_html($client['furigana']) ?>" />
			</td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['email']) : ?><tr>
			<th><?php echo apply_filters('booking_form_email', 'E-Mail') ?></th>
			<td>
				<?php echo esc_html($client['email']) ?>
				<input type="hidden" name="booking[client][email]" value="<?php echo esc_html($client['email']) ?>" />
			</td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['postcode']) : ?><tr>
			<th><?php echo apply_filters('booking_form_postcode', '年齢') ?></th>
			<td>
				<?php echo esc_html($client['postcode']) ?>
				<input type="hidden" name="booking[client][postcode]" value="<?php echo esc_html($client['postcode']) ?>" />
			</td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['address']) : ?><tr>
			<th><?php echo apply_filters('booking_form_address', '住所') ?></th>
			<td>
				<?php echo esc_html($client['address1']) . '<br />' . esc_html($client['address2']) ?>
				<input type="hidden" name="booking[client][address1]" value="<?php echo esc_html($client['address1']) ?>" />
				<input type="hidden" name="booking[client][address2]" value="<?php echo esc_html($client['address2']) ?>" />
			</td>
		</tr><?php endif; ?>
		<?php if (0 < $this->clcols['tel']) : ?><tr>
			<th><?php echo apply_filters('booking_form_tel', '電話番号') ?></th>
			<td>
				<?php echo esc_html($client['tel']) ?>
				<input type="hidden" name="booking[client][tel]" value="<?php echo esc_html($client['tel']) ?>" />
			</td>
		</tr><?php endif; ?>

		<tr>
			<td colspan="2"><?php echo apply_filters('booking_form_message_title', 'その他') ?></td>
		</tr>
		<tr>
			<th>内容</th>
			<td>
				<?php echo nl2br(esc_html($this->booking['note'])) ?>
				<input type="hidden" name="booking[note]" value="<?php echo esc_textarea($this->booking['note']) ?>" />
			</td>
		</tr>
	</table>
	</fieldset>

	<div id="action-button" style="text-align: center">
		<?php echo apply_filters('booking_form_submit_button', '<button type="submit" name="reserve_action" value="validate">予約する</button>'); ?>
	</div>
	<input type="hidden" name="nonce" value="<?php echo wp_create_nonce("{$this->domain}_" . self::PAGE_NAME) ?>" />
	<input type="hidden" name="booking[article_id]" value="<?php echo $this->article_id ?>" />
	<input type="hidden" name="booking[booking_time]" value="<?php echo $this->thetime ?>" />
	<input type="hidden" name="action" value="confirm" />
	<input type="hidden" name="booking[user_id]" value="<?php echo esc_html($this->booking['user_id']) ?>" />
</form>
</div>
<?php
		return ob_get_clean();
	}

	/**
	 * 対象予約品目の参照を戻す
	 *
	 */
	public function getArticle() {
		return $this->article;
	}

	/**
	 * 各種条件設定パラメータの参照を戻す
	 *
	 */
	public function getControls() {
		return $this->controls;
	}

}