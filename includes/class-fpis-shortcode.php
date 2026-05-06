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
		<div id="fpis-app-root" data-per-page="<?php echo esc_attr( $atts['per_page'] ); ?>">
			<div class="fpis-marketplace-container">
				<!-- Sidebar Navigation & Filters -->
				<aside class="fpis-sidebar">
					<div class="fpis-sidebar-header">
						<h2>Asset Exchange</h2>
						<p>Dữ liệu kiểm định real-time</p>
					</div>

					<div class="fpis-filter-group">
						<label class="fpis-filter-label">Tìm kiếm</label>
						<div class="fpis-input-group">
							<span class="dashicons dashicons-search"></span>
							<input type="text" id="fpis-search" class="fpis-field fpis-field-with-icon" placeholder="Tên fanpage, ID..." data-filter="search">
						</div>
					</div>

					<div class="fpis-filter-group">
						<label class="fpis-filter-label">Nền tảng & Khu vực</label>
						<select id="fpis-platform" class="fpis-field" data-filter="platform" style="margin-bottom:12px">
							<option value="">Tất cả nền tảng</option>
							<option value="facebook">Facebook</option>
							<option value="tiktok">TikTok</option>
						</select>
						
						<select id="fpis-region" class="fpis-field" data-filter="region">
							<option value="">Mọi khu vực</option>
							<option value="south">Miền Nam</option>
							<option value="north">Miền Bắc</option>
							<option value="central">Miền Trung</option>
							<option value="nationwide">Toàn quốc</option>
						</select>
					</div>

					<div class="fpis-filter-group">
						<label class="fpis-filter-label">Nhân khẩu học</label>
						<select id="fpis-gender" class="fpis-field" data-filter="dominant">
							<option value="">Tất cả giới tính</option>
							<option value="female">Cộng đồng Nữ</option>
							<option value="male">Cộng đồng Nam</option>
							<option value="balanced">Cân bằng</option>
						</select>
					</div>

					<div class="fpis-filter-group">
						<label class="fpis-filter-label">Khoảng Follow</label>
						<div class="fpis-range-grid">
							<input type="number" id="fpis-min-follow" class="fpis-field" placeholder="Min" data-filter="minFollow">
							<input type="number" id="fpis-max-follow" class="fpis-field" placeholder="Max" data-filter="maxFollow">
						</div>
					</div>

					<div class="fpis-sidebar-footer" style="margin-top:40px">
						<a href="<?php echo esc_url( get_option( 'fpis_contact_zalo', '#' ) ); ?>" target="_blank" class="fpis-view-btn" style="display:block; text-align:center; text-decoration:none">
							Liên hệ hỗ trợ
						</a>
					</div>
				</aside>

				<!-- Main Content -->
				<main class="fpis-main-content">
					<header class="fpis-stats-bar">
						<div class="fpis-results-count">Đang tải dữ liệu...</div>
						<div class="fpis-header-badge" style="background:var(--fpis-status-success); color:white; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700">
							LIVE ANALYTICS
						</div>
					</header>

					<div class="fpis-asset-grid">
						<!-- JS populated -->
					</div>
					
					<div id="fpis-pagination" style="margin-top:40px">
						<!-- JS populated -->
					</div>
				</main>
			</div>

			<!-- Refactored Modal System -->
			<div id="fpis-modal" class="fpis-modal-system">
				<div class="fpis-modal-backdrop"></div>
				<div class="fpis-modal-content">
					<div class="fpis-modal-placeholder">
						<!-- JS populated -->
					</div>
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
