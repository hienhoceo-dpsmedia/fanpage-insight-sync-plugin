<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPIS_Shortcode {

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'per_page'     => 20,
			'show_filters' => 'true',
		], $atts );

		ob_start();
		?>
		<div id="fpis-app" class="fpis-marketplace" data-per-page="<?php echo esc_attr( $atts['per_page'] ); ?>">
			<!-- Sidebar Navigation & Filters -->
			<aside class="fpis-sidebar">
				<div class="fpis-sidebar-header">
					<div class="fpis-brand">
						<span class="fpis-logo-icon">💠</span>
						<span class="fpis-brand-name">Asset Exchange</span>
					</div>
				</div>

				<nav class="fpis-nav">
					<div class="fpis-nav-group">
						<label>TÌM KIẾM</label>
						<div class="fpis-search-box">
							<input type="text" id="fpis-search" placeholder="Tên fanpage, ID...">
							<span class="dashicons dashicons-search"></span>
						</div>
					</div>

					<div class="fpis-nav-group">
						<label>BỘ LỌC TÀI SẢN</label>
						<select id="fpis-gender" class="fpis-sidebar-select">
							<option value="all">Tất cả giới tính</option>
							<option value="female">Cộng đồng Nữ</option>
							<option value="male">Cộng đồng Nam</option>
							<option value="balanced">Cân bằng</option>
						</select>

						<select id="fpis-region" class="fpis-sidebar-select">
							<option value="all">Mọi khu vực</option>
							<option value="south">Miền Nam</option>
							<option value="north">Miền Bắc</option>
							<option value="central">Miền Trung</option>
							<option value="nationwide">Toàn quốc</option>
						</select>
						
						<select id="fpis-platform" class="fpis-sidebar-select">
							<option value="all">Tất cả nền tảng</option>
							<option value="facebook">Facebook</option>
							<option value="tiktok">TikTok</option>
						</select>
					</div>

					<div class="fpis-nav-group">
						<label>KHOẢNG FOLLOW</label>
						<div class="fpis-range-inputs">
							<input type="number" id="fpis-min-follow" placeholder="Min">
							<span>-</span>
							<input type="number" id="fpis-max-follow" placeholder="Max">
						</div>
					</div>
				</nav>

				<div class="fpis-sidebar-footer">
					<a href="<?php echo esc_url( get_option( 'fpis_contact_zalo', '#' ) ); ?>" target="_blank" class="fpis-sidebar-btn">
						<span class="dashicons dashicons-whatsapp"></span> Hỗ trợ đại lý
					</a>
				</div>
			</aside>

			<!-- Main Content -->
			<main class="fpis-main">
				<header class="fpis-dashboard-header">
					<div class="fpis-header-title">
						<h1>Sàn Giao Dịch Tài Sản Số</h1>
						<p>Dữ liệu được kiểm định bởi AI thời gian thực</p>
					</div>
					<div class="fpis-header-stats">
						<div class="fpis-mini-stat">
							<span class="fpis-label">Hôm nay</span>
							<span class="fpis-value">+12 Assets</span>
						</div>
					</div>
				</header>

				<div class="fpis-content-area">
					<div id="fpis-results" class="fpis-grid">
						<!-- JS populated cards -->
					</div>
					
					<div id="fpis-pagination" class="fpis-pagination-container">
						<!-- JS populated pagination -->
					</div>
				</div>
			</main>
		</div>

		<!-- Audit Report Modal (Full Screen Detail) -->
		<div id="fpis-modal" class="fpis-modal">
			<div class="fpis-modal-overlay"></div>
			<div class="fpis-audit-report">
				<button class="fpis-modal-close">
					<span class="dashicons dashicons-no-alt"></span> Đóng báo cáo
				</button>
				<div id="fpis-modal-body">
					<!-- JS populated complex layout -->
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function register_routes() {
		register_rest_route( 'fpis/v1', '/pages', [
			'methods'  => 'GET',
			'callback' => [ $this, 'get_pages' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'fpis/v1', '/pages/(?P<id>\d+)', [
			'methods'  => 'GET',
			'callback' => [ $this, 'get_page_detail' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function get_pages( $request ) {
		$params = $request->get_params();
		$results = FPIS_DB::get_insights( $params );

		$is_admin = current_user_can( 'manage_options' );
		$is_logged_in = is_user_logged_in();

		foreach ( $results['data'] as &$row ) {
			$this->filter_row_by_role( $row, $is_logged_in, $is_admin );
		}

		return rest_ensure_response( $results );
	}

	public function get_page_detail( $request ) {
		$id = $request['id'];
		$row = FPIS_DB::get_insight( $id );
		if ( ! $row ) return new WP_Error( 'not_found', 'Page not found', [ 'status' => 404 ] );

		$is_admin = current_user_can( 'manage_options' );
		$is_logged_in = is_user_logged_in();

		$this->filter_row_by_role( $row, $is_logged_in, $is_admin );

		return rest_ensure_response( $row );
	}

	private function filter_row_by_role( &$row, $is_logged_in, $is_admin ) {
		// All can see basic info
		// Remove sensitive info if not admin
		if ( ! $is_admin ) {
			unset( $row->ai_raw_response );
			unset( $row->ai_error );
			unset( $row->ai_attempts );
			if ( ! $is_logged_in ) {
				unset( $row->male_pct );
				unset( $row->female_pct );
				unset( $row->age_dist_json );
				unset( $row->description );
				unset( $row->suitable_json );
				unset( $row->unsuitable_json );
			}
		}
	}
}
