<?php
if (!class_exists('MTSSB_Booking')) {
	require_once(dirname(__FILE__) . '/mtssb-booking.php');
}
/**
 * MTS Simple Booking Booking 予約登録・編集モジュール
 *
 * @Filename	mtssb-booking-admin.php
 * @Date		2012-04-30
 * @Author		S.Hayashi
 *
 * Updatet to 1.1.5 on 2012-12-04
 * Updated to 1.0.1 on 2012-09-14
 */

class MTSSB_Booking_Admin extends MTSSB_Booking {
	const VERSION = '1.1.5';
	const PAGE_NAME = 'simple-booking-booking';

	private static $iBooking = null;

	// 読み込んだ予約品目データ
	private $article_id;
	private $theyear;
	private $articles = null;		// 予約品目

	// 操作対象データ
	private $themonth = 0;		// 当該カレンダーのunix time
	private $aidx = '';			// 当該品目のカレンダーインデックス

	private $action = '';
	private $message = '';
	private $errflg = false;

	/**
	 * インスタンス化
	 *
	 */
	static function get_instance() {
		if (!isset(self::$iBooking)) {
			self::$iBooking = new MTSSB_Booking_Admin();
		}

		return self::$iBooking;
	}

	public function __construct() {
		global $mts_simple_booking;

		parent::__construct();

		// CSSロード
		$mts_simple_booking->enqueue_style();

		// Javascriptロード
		wp_enqueue_script("mtssb_booking_admin_js", $this->plugin_url . "js/mtssb-booking-admin.js", array('jquery'));
	}

	/**
	 * 管理画面メニュー処理
	 *
	 */
	public function booking_page() {

		$this->errflg = false;
		$this->message = '';

		// 予約品目
		$this->articles = MTSSB_Article::get_all_articles();

		if (isset($_POST['action'])) {
			$action = $_POST['action'];

			if (!wp_verify_nonce($_POST['nonce'], self::PAGE_NAME . "_{$action}")) {
				die("Nonce error!");
			}

			// 予約データを正規化し、登録データを取得する
			$this->booking = $this->normalize_booking($_POST['booking']);

			switch ($action) {
				case 'add' :
					$booking_id = $this->add_booking();
					if ($booking_id) {
						$this->message = __('Booking data has been added.', $this->domain);
						$this->booking = $this->new_booking($this->booking['booking_time'] - $this->booking['booking_time'] % 86400, $this->booking['article_id']);
					} else {
						$this->message = __('Booking data has been failed to add.', $this->domain);
						$this->errflg = true;
					}
					break;
				case 'save' :
					$booking_id = $this->save_booking();
					if ($booking_id) {
						$this->message = __('Booking data has been saved.', $this->domain);
						$this->booking['booking_id'] = $booking_id;
					} else {
						$this->message = __('Booking data has been failed to save.', $this->domain);
						$this->errflg = true;
					}
					break;
				default :
					break;
			}
		} else if (isset($_GET['action']) && $_GET['action'] == 'edit') {
			// 格納データを操作データオブジェクトに移す
			$this->booking = $this->array_merge_default($this->new_booking(), $this->get_booking(intval($_REQUEST['booking_id'])));
		} else {
			$daytime = isset($_GET['dt']) ? intval($_GET['dt']) : 0;
			$article_id = isset($_GET['article_id']) ? intval($_GET['article_id']) : 0;
			$this->booking = $this->new_booking($daytime, $article_id);
		}

		$action = $this->booking['booking_id'] ? 'save' : 'add';

?>
	<div class="wrap columns-2">
		<?php screen_icon('edit') ?>
		<h2><?php echo $action == 'save' ? __('Edit Booking', $this->domain) : __('Add Booking', $this->domain) ?></h2>
		<?php if (!empty($this->message)) : ?>
			<div class="<?php echo ($this->errflg) ? 'error' : 'updated' ?>"><p><strong><?php echo $this->message; ?></strong></p></div>
		<?php endif; ?>

		<form id="add-booking" method="post" action="?page=<?php echo self::PAGE_NAME ?>">
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">

					<div id="post-body-content">
						<?php $this->_postbox_booking() ?>
					</div>

					<div id="postbox-container-1" class="postbox-container">
						<!-- div id="side-sortables" class="meta-box-sortables ui-sortable" -->
							<div id="addsubmitdiv" class="postbox">
								<h3><?php echo $action == 'save' ? __('Edit Booking', $this->domain) : __('Add Booking', $this->domain) ?></h3>
								<div class="inside">
									<div id="minor-publishing">
										<div id="misc-publishing-actions">
											<div class="misc-pub-section">
												<label for="booking-confirmed"><?php _e('Booking Confirmation:', $this->domain) ?></label>
												<input type="hidden" name="booking[confirmed]" value="0" />
												<input id="booking-confirmed" type="checkbox" name="booking[confirmed]" value="1"<?php echo $this->booking['confirmed'] ? ' checked="checked"' : '' ?> />
											</div>
										</div>
									</div>
									
									<div id="major-publishing-actions">
										<?php if ($action == 'save') : ?><div id="delete-action">
											<a href="?page=simple-booking-list&amp;booking_id=<?php echo $this->booking['booking_id'] ?>&amp;action=delete&amp;nonce=<?php echo wp_create_nonce('simple-booking-list_delete') ?>" onclick="return confirm('<?php _e('Do you really want to delete this booking?', $this->domain) ?>')"><?php _e('Delete') ?></a>
										</div><?php endif; ?>
										<div id="publishing-action">
											<input id="publish" class="button-primary" type="submit" value="<?php echo $action == 'save' ? __('Save Booking', $this->domain) : __('Add Booking', $this->domain) ?>" name="save">
										</div>
										<div class="clear"> </div>
									</div>
									<div class="clear"> </div>
								</div>
							</div>
						<!-- /div -->
					</div>

					<div id="postbox-container-2" class="postbox-container">
						<?php $this->_postbox_options() ?>
						<?php $this->_postbox_client() ?>
						<?php $this->_postbox_note() ?>
					</div>

				</div>
			</div>
			<input type="hidden" name="action" value="<?php echo $action ?>" />
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce(self::PAGE_NAME . "_{$action}") ?>" />
		</form>

	</div><!-- wrap -->

<?php
		return;

	}

	/**
	 * 予約データ入力フォーム postbox
	 *
	 */
	private function _postbox_booking() {
		$odate = new MTS_WPDate;

?>
	<div class="postbox">
		<h3><?php _e('Booking Data', $this->domain) ?></h3>
		<div class="inside">
			<table class="form-table" style="width: 100%">
				<tr class="form-field">
					<th>
						<?php _e('Booking Date', $this->domain) ?>
					</th>
					<td>
						<input type="hidden" name="booking[booking_id]" value="<?php echo $this->booking['booking_id'] ?>" />
						<?php echo $odate->set_time($this->booking['booking_time'])->date_form('booking_time', 'booking') ?>
					</td>
				</tr>
				<tr class="form-field">
					<th>
						<?php _e('Booking Event', $this->domain) ?>
					</th>
					<td>
						<select id="booking-article" class="booking-select-article" name="booking[article_id]">
							<?php foreach ($this->articles as $article_id => $article) {
								echo "<option value=\"$article_id\"" . ($this->booking['article_id'] == $article_id ? ' selected="selected"' : '')
								 .">{$article['name']}</option>\n";
							} ?>
						</select>
						<select id="booking-time" class="booking-select-time" name="booking[timetable]">
							<?php
								reset($this->articles);
								$article_id = empty($this->booking['article_id']) ? key($this->articles) : $this->booking['article_id'];
								$timetable = $this->booking['booking_time'] % 86400;
								//if (0 < $this->booking['booking_time']) {
								//	$timetable = $this->booking['booking_time']
								//	 - mktime(0, 0, 0, date('n', $this->booking['booking_time']), date('j', $this->booking['booking_time']), date('Y', $this->booking['booking_time']));
								//}
								if (empty($this->articles[$article_id]['timetable'])) {
									echo '<option value="">' . __('Nothing', $this->domain) . "</option>\n";
								} else {
									foreach ($this->articles[$article_id]['timetable'] as $time) {
										echo "<option value=\"$time\"" . ($timetable == $time ? ' selected="selected"' : '') . ">" . date('H:i', $time) . "</option>\n";
									}
								} ?>
						</select>
						<span id="loader-img" style="display:none"><img src="<?php echo $this->plugin_url . 'image/ajax-loader.gif' ?>" alt="Loading" /></span>
						<input type="hidden" id="ajax-nonce" value="<?php echo wp_create_nonce($this->domain . '_ajax') ?>" />
					</td>
				</tr>
				<tr>
					<th>
						メニュー
					</th>
					<td>
						現在：<?php echo $this->booking['client']['course'] ?>
						<br />
	変更：<select name="booking[client][course]" value="<?php echo $this->booking['client']['course'] ?>">
		<option><?php echo $this->booking['client']['course'] ?></option>
		<? /*	foreachとかでできたらいいのに　*/?>
		<option>メニューA</option>
		<option>メニューB</option>
		<option>メニューC</option>
		<option>メニューD</option>
		<option>メニューE</option>
	</select>

					</td>
				</tr>
				<tr>
					<th>
					オプションメニュー
					</th>
					<td>
        <ul>
        <li><input type="checkbox" name="booking[client][option1]" <?php if ($this->booking['client']['option1'] != NULL ){ ?>value="オプション1"  checked <? } ?>/> オプション1</li>
        <li><input type="checkbox" name="booking[client][option2]" <?php if ($this->booking['client']['option2'] != NULL ){ ?>value="オプション2"  checked <? } ?>/> オプション2</li>
        <li><input type="checkbox" name="booking[client][option3]" <?php if ($this->booking['client']['option3'] != NULL ){ ?>value="オプション3"  checked <? } ?>/> オプション3</li>
        <li><input type="checkbox" name="booking[client][option4]" <?php if ($this->booking['client']['option4'] != NULL ){ ?>value="オプション4"  checked <? } ?>/> オプション4</li>
        <li><input type="checkbox" name="booking[client][option5]" <?php if ($this->booking['client']['option5'] != NULL ){ ?>value="オプション5"  checked <? } ?>/> オプション5</li>
        <li><input type="checkbox" name="booking[client][option6]" <?php if ($this->booking['client']['option6'] != NULL ){ ?>value="オプション6"  checked <? } ?>/> オプション6</li>
        <li><input type="checkbox" name="booking[client][option7]" <?php if ($this->booking['client']['option7'] != NULL ){ ?>value="オプション7"  checked <? } ?>/> オプション7</li>
        <li><input type="checkbox" name="booking[client][option8]" <?php if ($this->booking['client']['option8'] != NULL ){ ?>value="オプション8"  checked <? } ?>/> オプション8</li>
        <li><input type="checkbox" name="booking[client][option9]" <?php if ($this->booking['client']['option9'] != NULL ){ ?>value="オプション9"  checked <? } ?>/> オプション9</li>
		</ul>
					</td>
				</tr>
				<tr>
					<th>
						<?php _e('Attendance', $this->domain) ?>
					</th>
					<td>
						<input id="booking-attendance" class="small-text" type="text" name="booking[number]" value="<?php echo $this->booking['number'] ?>" /> 人
					</td>
				</tr>
			</table>
		</div>
	</div>

<?php
	}

	/**
	 * 予約オプション入力フォーム postbox
	 *
	 */
	private function _postbox_options() {
		$odate = new MTS_WPDate;
		$opt_number = 0;

?>
<!--
	<div class="postbox">
		<h3><?php _e('Select Options', $this->domain) ?></h3>
		<div class="inside">
			<p>
				<?php _e('The option which can be chosen has nothing.', $this->domain) ?>
			</p>
		</div>
	</div>
-->
<?php
	}

	/**
	 * 予約者情報入力フォーム postbox
	 *
	 */
	private function _postbox_client() {
		$client = &$this->booking['client'];

?>
	<div class="postbox">
		<h3><?php _e('Client Information', $this->domain) ?></h3>
		<div class="inside">
			<table class="form-table" style="width: 100%">
<!--
				<tr>
					<th>
						<label for="booking-company"><?php _e('Company', $this->domain) ?></label>
					</th>
					<td>
						<input id="booking-company" class="mts-fat" type="text" name="booking[client][company]" value="<?php echo esc_attr($client['company']) ?>" />
					</td>
				</tr>
-->
				<tr>
					<th>
						<label for="booking-name"><?php _e('Name') ?></label>
					</th>
					<td>
						<input id="booking-name" class="mts-fat" type="text" name="booking[client][name]" value="<?php echo esc_attr($client['name']) ?>" />
					</td>
				</tr>
				<tr>
					<th>
						<label for="booking-furigana"><?php _e('Furigana', $this->domain) ?></label>
					</th>
					<td>
						<input id="booking-furigana" class="mts-fat" type="text" name="booking[client][furigana]" value="<?php echo esc_attr($client['furigana']) ?>" />
					</td>
				</tr>
				<tr>
					<th>
						<label for="booking-email">E-Mail</label>
					</th>
					<td>
						<input id="booking-email" type="text" name="booking[client][email]" value="<?php echo esc_attr($client['email']) ?>" />
					</td>
				</tr>
<!--
				<tr>
					<th>
						<label for="booking-postcode"><?php _e('Postcode', $this->domain) ?></label>
					</th>
					<td>
						<input id="booking-postcode" class="mts-small" type="text" name="booking[client][postcode]" value="<?php echo esc_attr($client['postcode']) ?>" />
					</td>
				</tr>
				<tr>
					<th>
						<label for="booking-address1"><?php _e('Address', $this->domain) ?></label>
					</th>
					<td>
						<input id="booking-address1" type="text" name="booking[client][address1]" value="<?php echo esc_attr($client['address1']) ?>" /><br />
						<input id="booking-address2" type="text" name="booking[client][address2]" value="<?php echo esc_attr($client['address2']) ?>" />
					</td>
				</tr>
-->
				<tr>
					<th>
						<label for="booking-tel"><?php _e('Phone number', $this->domain) ?></label>
					</th>
					<td>
						<input id="booking-tel" class="mts-middle" type="text" name="booking[client][tel]" value="<?php echo esc_attr($client['tel']) ?>" />
					</td>
				</tr>
				<tr>
					<th>
						<label for="booking-adult"><?php _e('Numbers', $this->domain) ?></label>
					</th>
					<td>
						大人<input id="booking-adult" class="small-text" type="text" name="booking[client][adult]" value="<?php echo esc_attr($client['adult']) ?>" />人　
						子供<input class="small-text" type="text" name="booking[client][child]" value="<?php echo esc_attr($client['child']) ?>" />人　
						幼児<input class="small-text" type="text" name="booking[client][baby]" value="<?php echo esc_attr($client['baby']) ?>" />人　
						車<input class="small-text" type="text" name="booking[client][car]" value="<?php echo esc_attr($client['car']) ?>" />台
					</td>
				</tr>

			</table>
		</div>
	</div>

<?php
	}

	/**
	 * メッセージ等注記 postbox
	 *
	 */
	private function _postbox_note() {

?>
	<div class="postbox">
		<h3><?php _e('Note', $this->domain) ?></h3>
		<div class="inside">
			<table class="form-table" style="width: 100%">
				<tr class="form-field">
					<th>
						<label for="booking-note"><?php _e('Note', $this->domain) ?></label>
					</th>
					<td>
						<textarea id="booking-note" name="booking[note]" rows="8" cols="50"><?php echo esc_textarea($this->booking['note']) ?></textarea>
					</td>
				</tr>
			</table>
		</div>
	</div>

<?php
	}

}
