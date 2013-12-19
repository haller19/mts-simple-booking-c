<?php
/**
 * MTS Simple Booking 管理予約システム 各種設定管理モジュール
 *
 * @filename	mtssb-settings-admin.php
 * @date		2012-04-23
 * @author		S.Hayashi
 *
 * Updated to 1.1.5 on 2012-12-02
 * Updated to 1.1.0 on 2012-11-02
 */
class MTSSB_Settings_Admin {
	const VERSION = '1.1.5';
	const PAGE_NAME = 'simple-booking-settings';

	/**
	 * Instance of this object module
	 */
	static private $iSettings = null;

	/**
	 * Private valiable
	 */
	private $domain;

	private $data = array();	// Option data of reading

	private $tab = '';			// Current Tab

	// 設定ページタブ
	private $tabs = array('controls', 'premise', 'reserve', 'miscellaneous');

	/**
	 * インスタンス化
	 *
	 */
	static function get_instance() {
		if (!isset(self::$iSettings)) {
			self::$iSettings = new MTSSB_Settings_Admin();
		}

		return self::$iSettings;
	}


	protected function __construct() {

		$this->domain = MTS_Simple_Booking::DOMAIN;

		// オプションデータ保存のためのホワイトリスト登録
		if (isset($_POST['mts_page_tag'])) {
			$option_name = $_POST['option_page'];
			$tab = substr($_POST['option_page'], strlen($this->domain . '_'));
			register_setting($option_name, $option_name, array($this, "{$tab}_validate"));
			return;
		}

		// オプションデータの新規登録・更新
		$this->_install_option();
	}

	/**
	 * Option page html lounched from admin menu 'General Settings'
	 *
	 */
	public function settings_page() {

		if (isset($_GET['tab']) && in_array($_GET['tab'], $this->tabs)) {
			$this->tab = $_GET['tab'];
		} else {
			$this->tab = $this->tabs[0];
		}

		$option_name = "{$this->domain}_{$this->tab}";

		$this->data = get_option($option_name);

		// 当該タブページの設定
		add_settings_section($option_name, $this->_option_title(), array($this, 'add_fields_settings'), $option_name);
		//$this->add_fields_settings();
?>
	<div class="wrap">
	<?php screen_icon('options-general') ?>
	<h2><?php _e('Setting Parameters', $this->domain); ?></h2>
	<h3 class="nav-tab-wrapper">
		<?php foreach ($this->tabs as $tb) : ?>
			<a class="nav-tab<?php echo $this->tab == $tb ? ' nav-tab-active' : '' ?>" href="<?php echo admin_url('admin.php?page=' . self::PAGE_NAME . "&amp;tab={$tb}") ?>"><?php echo $this->_tab_caption($tb) ?></a>
		<?php endforeach; ?>
	</h3>
	<?php settings_errors() ?>

	<form method="post" action="options.php">
		<?php settings_fields($option_name) ?>
		<?php do_settings_sections($option_name) ?>
		<?php submit_button() ?>
		<input type="hidden" name="mts_page_tag" value="<?php echo self::PAGE_NAME ?>" />
	</form>
	<?php $this->_footer_description() ?>
	</div>

<?php
	}


	/**
	 * Validate Settings
	 *
	 */
	public function controls_validate($input) {
		return $input;
	}

	public function miscellaneous_validate($input) {
		return $input;
	}

	public function premise_validate($input) {
		return $input;
	}

	public function reserve_validate($input) {
		return $input;
	}

	public function contact_validate($input) {
		return $input;
	} 

	/**
	 * タブ見出し
	 *
	 */
	private function _tab_caption($tab) {
		$captions = array(
			'controls' => __('Booking Paramters', $this->domain),
			'premise' => __('Premise Informations', $this->domain),
			'reserve' => __('Booking Mail', $this->domain),
			'miscellaneous' => __('Miscellaneous', $this->domain),
		);

		return $captions[$tab];
	}

	/**
	 * タブページのタイトル
	 *
	 */
	private function _option_title() {
		$titles = array(
			'controls' => __('Booking control parameters', $this->domain),
			'premise' => __('Information of the premise', $this->domain),
			'reserve' => __('Reply mail sentences for booking', $this->domain),
			'miscellaneous' => __('Other settings', $this->domain),
		);

		return $titles[$this->tab];
	}

	/**
	 * Add settings' fields to the section
	 *
	 */
	public function add_fields_settings() {
		$option_name = "{$this->domain}_{$this->tab}";
		if ($this->tab == 'controls') {
			add_settings_field('available', __('Booking available', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'available'));
			add_settings_field('closed_page', __('Closed Page', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'closed_page'));
			add_settings_field('start_accepting', __('Start accepting', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'start_accepting'));
			add_settings_field('output_margin', __('Output in the margin', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'output_margin'));
			add_settings_field('period', __('Period', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'period'));
			add_settings_field('vacant_mark', __('Vacant Mark', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'vacant_mark'));
			add_settings_field('low_mark', __('Low Mark', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'low_mark'));
			add_settings_field('full_mark', __('Full Mark', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'full_mark'));
			add_settings_field('disable', __('Disable Mark', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'disable'));
			add_settings_field('vacant_rate', __('Vacant Rate', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'vacant_rate'));
			add_settings_field('count', __('Count Number', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'count'));
			add_settings_field('message', __('Message', $this->domain), array($this, 'controls_form'),
				$option_name, $option_name, array('label_for' => 'message'));

		} else if ($this->tab == 'premise') {
			add_settings_field('name', __('The name of premise', $this->domain), array($this, 'premise_form'),
				$option_name, $option_name, array('label_for' => 'name'));
			add_settings_field('postcode', __('Postcode', $this->domain), array($this, 'premise_form'),
				$option_name, $option_name, array('label_for' => 'postcode'));
			add_settings_field('address1', __('Address 1', $this->domain), array($this, 'premise_form'),
				$option_name, $option_name, array('label_for' => 'address1'));
			add_settings_field('address2', __('Address 2', $this->domain), array($this, 'premise_form'),
				$option_name, $option_name, array('label_for' => 'address2'));
			add_settings_field('tel', __('Tel', $this->domain), array($this, 'premise_form'),
				$option_name, $option_name, array('label_for' => 'tel'));
			add_settings_field('fax', __('Fax', $this->domain), array($this, 'premise_form'),
				$option_name, $option_name, array('label_for' => 'fax'));
			add_settings_field('email', __('E-Mail', $this->domain), array($this, 'premise_form'),
				$option_name, $option_name, array('label_for' => 'email'));
			add_settings_field('mobile', __('Mobile Mail', $this->domain), array($this, 'premise_form'),
				$option_name, $option_name, array('label_for' => 'mobile'));
			add_settings_field('web', __('Web Site URL', $this->domain), array($this, 'premise_form'),
				$option_name, $option_name, array('label_for' => 'web'));

		} else if ($this->tab == 'reserve') {
			add_settings_field('column', __('Column Setting', $this->domain), array($this, 'reserve_form'),
				$option_name, $option_name, array('label_for' => 'column'));
			add_settings_field('title', __('Subject', $this->domain), array($this, 'reserve_form'),
				$option_name, $option_name, array('label_for' => 'title'));
			add_settings_field('header', __('Mail Header', $this->domain), array($this, 'reserve_form'),
				$option_name, $option_name, array('label_for' => 'header'));
			add_settings_field('footer', __('Mail Footer', $this->domain), array($this, 'reserve_form'),
				$option_name, $option_name, array('label_for' => 'footer'));

		} else if ($this->tab == 'miscellaneous') {
			add_settings_field('adminbar', __('Admin Bar', $this->domain), array($this, 'miscellaneous_form'),
				$option_name, $option_name, array('label_for' => 'adminbar'));
		}
	}

	/**
	 * Controls form
	 *
	 */
	public function controls_form($args) {
		$priorities = array('capacity', 'quantity');

		switch ($args['label_for']) {
			case 'available' : ?>
				<input type="hidden" id="available_" name="mts_simple_booking_controls[available]" value="0" />
				<input id="available" name="mts_simple_booking_controls[available]" value="1" type="checkbox"<?php echo $this->data['available'] ? ' checked="checked"' : '' ?> /><br />
				<?php _e('Uncheck to stop displaying and accepting reservations.', $this->domain);
				break;
			case 'closed_page' : ?>
				<input id="closed_page" name="mts_simple_booking_controls[closed_page]" type="text" value="<?php echo esc_html($this->data['closed_page']) ?>" /><br />
				<?php _e('Input the closed message to display.', $this->domain); 
				break;
			case 'start_accepting' :
				$accepting = $this->_get_booking_margin(); ?>
				<select id="start_accepting" name="mts_simple_booking_controls[start_accepting]" style="letter-spacing:2px"><?php foreach ($accepting as $min => $label) : ?>
					<option value="<?php echo $min ?>"<?php echo $min == $this->data['start_accepting'] ? ' selected="selected"' : '' ?>><?php echo $label ?></option>
				<?php endforeach; ?></select> <?php _e('after', $this->domain) ?><br />
				<?php _e('The reservation is started to accept after selected time.', $this->domain);
				break;
			case 'output_margin' : ?>
				<label id="output_margin_out"><input type="radio" name="mts_simple_booking_controls[output_margin]" value="1"<?php echo $this->data['output_margin'] ? ' checked="checked"' : '' ?> /><?php _e('Output mark', $this->domain) ?></label>&nbsp;
				<label id="disable_margin_out"><input type="radio" name="mts_simple_booking_controls[output_margin]" value="0"<?php echo empty($this->data['output_margin']) ? ' checked="checked"' : '' ?> /><?php _e('Disable mark', $this->domain) ?></label><br />
				<?php _e('Mark out or disable in the margin of accepting.', $this->domain);
				break;
			case 'period' : ?>
				<select id="period" name="mts_simple_booking_controls[period]" style="letter-spacing:2px"><?php for ($i = 1; $i <= 6; $i++) : ?>
					<option value="<?php echo $i ?>"<?php echo $i == $this->data['period'] ? ' selected="selected"' : '' ?>><?php echo $i ?></option>
				<?php endfor; ?></select> <?php _e('months', $this->domain) ?><br />
				<?php _e('Number of months that you accept bookings in the future.', $this->domain);
				break;
			case 'vacant_mark' : ?>
				<input id="vacant_mark" type="text" name="mts_simple_booking_controls[vacant_mark]" value="<?php echo esc_html($this->data['vacant_mark']) ?>" /><br />
				<?php _e("The mark which shows that there are enough openings of reservation.", $this->domain);
				break;
			case 'low_mark' : ?>
				<input id="low_mark" type="text" name="mts_simple_booking_controls[low_mark]" value="<?php echo esc_html($this->data['low_mark']) ?>" /><br />
				<?php _e("The mark which shows that there are few openings of reservation.", $this->domain);
				break;
			case 'full_mark' : ?>
				<input id="full_mark" type="text" name="mts_simple_booking_controls[full_mark]" value="<?php echo esc_html($this->data['full_mark']) ?>" /><br />
				<?php _e("The mark which shows that reservation is full and it cannot reserve.", $this->domain);
				break;
			case 'disable' : ?>
				<input id="disable" type="text" name="mts_simple_booking_controls[disable]" value="<?php echo esc_html($this->data['disable']) ?>" /><br />
				<?php _e("The mark which shows not accepting reservation.", $this->domain);
				break;
			case 'vacant_rate' : ?>
				<input id="vacant_rate" type="text" name="mts_simple_booking_controls[vacant_rate]" value="<?php echo esc_html($this->data['vacant_rate']) ?>" style="width:3em" /><br />
				<?php _e("Full mark is displayed until the rate becomes to the percentage.", $this->domain);
				break;
			case 'count' : ?>
				<input type="hidden" name="mts_simple_booking_controls[count][adult]" value="0" />
				<label><?php _e('Adult', $this->domain) ?>:<input id="count" type="checkbox" name="mts_simple_booking_controls[count][adult]" value="1"<?php echo $this->data['count']['adult'] ? ' checked="checked"' : '' ?> /></label>
				<input type="hidden" name="mts_simple_booking_controls[count][child]" value="0" />
				<label><?php _e('Child', $this->domain) ?>:<input type="checkbox" name="mts_simple_booking_controls[count][child]" value="1"<?php echo $this->data['count']['child'] ? ' checked="checked"' : '' ?> /></label>
				<input type="hidden" name="mts_simple_booking_controls[count][baby]" value="0" />
				<label><?php _e('Baby', $this->domain) ?>:<input type="checkbox" name="mts_simple_booking_controls[count][baby]" value="1"<?php echo $this->data['count']['baby'] ? ' checked="checked"' : '' ?> /></label>
				<input type="hidden" name="mts_simple_booking_controls[count][car]" value="0" />
				<label><?php _e('Car', $this->domain) ?>:<input type="checkbox" name="mts_simple_booking_controls[count][car]" value="1"<?php echo $this->data['count']['car'] ? ' checked="checked"' : '' ?> /></label><br />
				<?php _e('Checked items is included in reservation form page.', $this->domain);
				break;
			case 'message' : ?>
				<input type="hidden" name="mts_simple_booking_controls[message][temps_utile]" value="0" />
				<label><input id="message" name="mts_simple_booking_controls[message][temps_utile]" value="1" type="checkbox"<?php echo $this->data['message']['temps_utile'] ? ' checked="checked"' : '' ?> /> <?php _e('Entrance and exit schedule', $this->domain) ?></label><br />
				<?php _e('Check to use the input item of entrance and exit schedule time.', $this->domain);
				break;
			default :
				break;
		}
	}

	/**
	 * Miscellaneous form
	 *
	 */
	public function miscellaneous_form($args) {
		switch ($args['label_for']) {
			case 'adminbar' : ?>
				<label id="adminbar_off"><input type="radio" name="mts_simple_booking_miscellaneous[adminbar]" value="0"<?php echo empty($this->data['adminbar']) ? ' checked="checked"' : '' ?> /><?php _e('Enable', $this->domain) ?></label>&nbsp;
				<label id="adminbar_on"><input type="radio" name="mts_simple_booking_miscellaneous[adminbar]" value="1"<?php echo $this->data['adminbar'] ? ' checked="checked"' : '' ?> /><?php _e('Disable', $this->domain) ?></label><br />
				<?php _e('Display adminbar out or not on front end page.', $this->domain);
				break;
			default :
				break;
		}
	}

	/**
	 * Premise Form
	 *
	 */
	public function premise_form($args) {
		switch ($args['label_for']) {
			case 'name' : ?>
				<input id="name" type="text" name="mts_simple_booking_premise[name]" value="<?php echo esc_html($this->data['name']) ?>" style="width:80%" />
				<?php break;
			case 'postcode' : ?>
				<input id="postcode" type="text" name="mts_simple_booking_premise[postcode]" value="<?php echo esc_html($this->data['postcode']) ?>" class="30%" />
				<?php break;
			case 'address1' : ?>
				<input id="address1" type="text" name="mts_simple_booking_premise[address1]" value="<?php echo esc_html($this->data['address1']) ?>" style="width:80%" />
				<?php break;
			case 'address2' : ?>
				<input id="address2" type="text" name="mts_simple_booking_premise[address2]" value="<?php echo esc_html($this->data['address2']) ?>" style="width:80%" />
				<?php break;
			case 'tel' : ?>
				<input id="tel" type="text" name="mts_simple_booking_premise[tel]" value="<?php echo esc_html($this->data['tel']) ?>" style="width:30%" />
				<?php break;
			case 'fax' : ?>
				<input id="fax" type="text" name="mts_simple_booking_premise[fax]" value="<?php echo esc_html($this->data['fax']) ?>" style="width:30%" />
				<?php break;
			case 'email' : ?>
				<input id="email" type="text" name="mts_simple_booking_premise[email]" value="<?php echo esc_html($this->data['email']) ?>" style="width:80%" /><br />
				<?php _e("e.g. webmaster@example.com", $this->domain);
				break;
			case 'mobile' : ?>
				<input id="mobile" type="text" name="mts_simple_booking_premise[mobile]" value="<?php echo esc_html($this->data['mobile']) ?>" style="width:80%" /><br />
				<?php _e("A mobile phone will be mailed if its mail address is inputed, and reservation enters.", $this->domain);
				break;
			case 'web' : ?>
				<input id="web" type="text" name="mts_simple_booking_premise[web]" value="<?php echo esc_html($this->data['web']) ?>" style="width:80%" /><br />
				<?php _e("e.g. http://www.example.com", $this->domain);
				break;
			default :
				break;
		}
	}

	/**
	 * Reservation Mail Option Format
	 *
	 */
	public function reserve_form($args) {
		switch ($args['label_for']) {
			case 'column' :
				$options = array(__('Unnecessary', $this->domain), __('Required', $this->domain), __('Arbitrary', $this->domain));
				$items = $this->_get_default('reserve');
				foreach ($items['column'] as $colname => $val) : ?>
					<p><?php _e(ucwords($colname), $this->domain) ?><br />
					<select id="client_column" name="mts_simple_booking_reserve[column][<?php echo $colname ?>]">
						<?php foreach ($options as $key => $optname) : ?>
						<option value="<?php echo $key ?>"<?php echo isset($this->data['column'][$colname]) && $this->data['column'][$colname] == $key ? ' selected="selected"' : '' ?>><?php echo $optname ?></option>
						<?php endforeach; ?>
					</select></p>
				<?php endforeach;
				break;
			case 'title' : ?>
				<input id="client_title" class="regular-text" type="text" name="mts_simple_booking_reserve[title]" value="<?php echo esc_html($this->data['title']) ?>" /><br />
				<?php _e("The subject of the automatic reply mail when reserving on the site.", $this->domain);
				break;
			case 'header' : ?>
				<textarea id="client_header" class="large-text" cols="60" rows="12" name="mts_simple_booking_reserve[header]"><?php echo esc_textarea($this->data['header']) ?></textarea><br />
				<?php _e("Above sentence of the automatic reply mail.", $this->domain);
				break;
			case 'footer' : ?>
				<textarea id="client_footer" class="large-text" cols="60" rows="12" name="mts_simple_booking_reserve[footer]"><?php echo esc_textarea($this->data['footer']) ?></textarea><br />
				<?php _e("Below sentence of the automatic reply mail.", $this->domain);
				break;
			default :
				break;
		}
	}

	/**
	 * Output footer description of the option
	 *
	 */
	private function _footer_description() {
		if ($this->tab == 'reserve') : ?>
			<p><?php _e("The following variables can be used in Mail Header and Footer.", $this->domain) ?></p>
			<ul class="ul-description">
				<li>%CLIENT_NAME%</br><?php _e("Reservation application guest's name.", $this->domain) ?></li>
				<li>%RESERVE_ID%</br><?php _e("Reservation ID generated automatically.", $this->domain) ?></li>
				<li>%NAME%</br><?php _e("Shop Name", $this->domain) ?></li>
				<li>%POSTCODE%</br><?php _e("Post Code", $this->domain) ?></li>
				<li>%ADDRESS%</br><?php _e("Address", $this->domain) ?></li>
				<li>%TEL%</br><?php _e("TEL Number", $this->domain) ?></li>
				<li>%FAX%</br><?php _e("FAX Number", $this->domain) ?></li>
				<li>%EMAIL%</br><?php _e("E-Mail", $this->domain) ?></li>
				<li>%WEB%</br><?php _e("Web Site", $this->domain) ?></li>
			</ul>
		<?php elseif ($this->tab == 'contact') : ?>
			<p><?php _e("The following variables can be used in Mail Header and Footer.", $this->domain) ?></p>
			<ul class="ul-description">
				<li>%CLIENT_NAME%</br><?php _e("Reservation application guest's name.", $this->domain) ?></li>
				<li>%NAME%</br><?php _e("Shop Name", $this->domain) ?></li>
				<li>%POSTCODE%</br><?php _e("Post Code", $this->domain) ?></li>
				<li>%ADDRESS%</br><?php _e("Address", $this->domain) ?></li>
				<li>%TEL%</br><?php _e("TEL Number", $this->domain) ?></li>
				<li>%FAX%</br><?php _e("FAX Number", $this->domain) ?></li>
				<li>%EMAIL%</br><?php _e("E-Mail", $this->domain) ?></li>
				<li>%WEB%</br><?php _e("Web Site", $this->domain) ?></li>
			</ul>
		<?php endif;
	}

	/**
	 * オプションデータを新規登録、更新する
	 *
	 */
	private function _install_option() {

		foreach ($this->tabs as $tab) {

		// 登録オプションデータとモジュールの初期オプションデータを読込む
			$option_name = "{$this->domain}_$tab";

			$option = get_option($option_name);
			$default = $this->_get_default($tab);

			// 未登録なら新規登録する
			if (empty($option)) {
				add_option($option_name, $default);
				continue;
			}

			// 新旧オプションデータを比較し、異なるキーがあれば更新する
			$new_keys = array_keys($default);
			sort($new_keys);
			$opt_keys = array_keys($option);
			sort($opt_keys);
			if ($new_keys == $opt_keys) {
				continue;
			}

			// オプションデータの更新
			foreach ($default as $key => $val) {
				if (array_key_exists($key, $option)) {
					$default[$key] = $option[$key];
				}
			}
			update_option($option_name, $default);
		}
	}

	/**
	 * 設定オプションデータの初期値取得
	 *
	 */
	private function _get_default($tab) {

		$options = array(
			'controls' => array(
				'available' => 0,			// 予約受付中
				'closed_page' => '受付は終了しました。',	// 予約受付中止中のメッセージ
				'start_accepting' => 1440,	// 予約受付開始日
				'output_margin' => 0,		// 受け付け開始マージンのマーク表示
				'period' => 6,				// 予約受付期間
				'vacant_mark' => '○',		// 予約カレンダー受付中記号
				'low_mark' => '△',			// 予約カレンダー残数僅少記号
				'full_mark' => '×',		// 予約カレンダー受付終了記号
				'disable' => '－',			// 予約カレンダー予約不可記号
				'vacant_rate' => 30,		// 残数僅少を表示する残数率(%)
				'count' => array(
					'adult' => 1,
					'child' => 0,
					'baby' => 0,
					'car' => 0,
				),
				'message' => array(
					'temps_utile' => 0,	// 入退場時間の入力
				),
			),

			'miscellaneous' => array(
				'adminbar' => 0,		// Admin bar 非表示
			),

			'premise' => array(
				'name' => '施設名',
				'postcode' => '年齢',
				'address1' => '住所',
				'address2' => '',
				'tel' => '電話番号',
				'fax' => '',
				'email' => 'メールアドレス',
				'mobile' => '',
				'web' => 'http://www.example.com',
			),

			'reserve' => array(
				'column' => array(		// 0:不要 1:必須 2:任意
					'course' => 1, //コース追加
					'option' => 1, //オプション追加
					'option1' => 1, //オプション追加
					'option2' => 1, //オプション追加
					'option3' => 1, //オプション追加
					'option4' => 1, //オプション追加
					'option5' => 1, //オプション追加
					'option6' => 1, //オプション追加
					'option7' => 1, //オプション追加
					'option8' => 1, //オプション追加
					'option9' => 1, //オプション追加
					'company' => 1,
					'name' => 1,
					'furigana' => 2,
					'email' => 1,
					'postcode' => 2,
					'address' => 2,
					'tel' => 1,
				),
				'title' => '【ご予約を承りました】',
				'header' => "%CLIENT_NAME% 様\n"
					 . "ご予約ID：%RESERVE_ID%\n\n"
					 . "当%NAME%をご利用いただき誠にありがとうございます。\n\n"
					 . "以下の内容でご予約を承りました。詳細が確認できましたら後ほど予\n"
					 . "約完了のメールをお送りします。なお、内容に不明な点などあった場\n"
					 . "合、こちらからお問合わせさせていただく場合がございますのでご了\n"
					 . "承下さい。\n\n"
					 . "翌日になりましても完了メールをお受け取りできないようでしたら、\n"
					 . "回線事情などによりデータの喪失も考えられますので、お手数をお掛\n"
					 . "けしますがご連絡下さいますようお願い申し上げます。\n\n",
				'footer' => "このメールにお心当たりが無い場合、以下へご連絡下さるよう\n"
					 . "お願い申し上げます。\n\n"
					 . "%NAME%\n"				// 施設名
					 . "%POSTCODE%\n"			// 郵便番号
					 . "%ADDRESS%\n"			// 住所
					 . "TEL: %TEL%\n"			// TEL
					 . "E-Mail: %EMAIL%\n"		// E-Mail
					 . "Webサイト: %WEB%",		// Webサイト
			),
		);

		return $options[$tab];
	}

	/**
	 * 予約のマージン選択肢取得
	 *
	 */
	private function _get_booking_margin()
	{
		return apply_filters('mtssb_settings_get_booking_margin', array(
			'10' => __('10 Minutes', $this->domain),
			'30' => __('30 Minutes', $this->domain),
			'60' => __('1 Hour', $this->domain),
			'180' => __('3 Hours', $this->domain),
			'360' => __('6 Hours', $this->domain),
			'720' => __('12 Hours', $this->domain),
			'1440' => __('1 Day', $this->domain),
			'2880' => __('2 Days', $this->domain),
			'4320' => __('3 Days', $this->domain),
			'5760' => __('4 Days', $this->domain),
			'7200' => __('5 Days', $this->domain),
			'8640' => __('6 Days', $this->domain),
		));
	}

}
