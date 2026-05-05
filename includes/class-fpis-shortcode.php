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
		<div id="fpis-app" class="fpis-container" data-per-page="<?php echo esc_attr( $atts['per_page'] ); ?>">
			<?php if ( $atts['show_filters'] === 'true' ) : ?>
				<div class="fpis-filters">
					<input type="text" id="fpis-search" placeholder="Tìm tên fanpage...">
					<select id="fpis-gender">
						<option value="all">Tất cả giới tính</option>
						<option value="female">Nữ</option>
						<option value="male">Nam</option>
						<option value="balanced">Cân bằng</option>
					</select>
					<select id="fpis-region">
						<option value="all">Tất cả khu vực</option>
						<option value="south">Miền Nam</option>
						<option value="north">Miền Bắc</option>
						<option value="central">Miền Trung</option>
						<option value="nationwide">Toàn quốc</option>
					</select>
				</div>
			<?php endif; ?>

			<div class="fpis-table-wrapper">
				<table id="fpis-table">
					<thead>
						<tr>
							<th>Tên trang</th>
							<th>Followers</th>
							<th>Khu vực</th>
							<?php if ( is_user_logged_in() ) : ?>
								<th>Giới tính</th>
								<th>Độ tuổi</th>
								<th>Tình trạng</th>
							<?php endif; ?>
							<th>Hành động</th>
						</tr>
					</thead>
					<tbody id="fpis-results">
						<!-- JS populated -->
					</tbody>
				</table>
			</div>
			<div id="fpis-pagination"></div>
		</div>

		<!-- Detail Modal -->
		<div id="fpis-modal" class="fpis-modal">
			<div class="fpis-modal-content">
				<span class="fpis-modal-close">&times;</span>
				<div id="fpis-modal-body"></div>
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
