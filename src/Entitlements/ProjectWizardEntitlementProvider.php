<?php
/**
 * Makes the add-on visible through Coordina's entitlement surface.
 */

declare(strict_types=1);

namespace CoordinaProjectWizard\Entitlements;

use Coordina\Platform\Contracts\EntitlementProviderInterface;

final class ProjectWizardEntitlementProvider implements EntitlementProviderInterface {
	public function resolve( array $local_state ): array {
		unset( $local_state );

		$can_access = current_user_can( 'coordina_run_project_wizard' );

		return array(
			'features' => array(
				'project_wizard' => array(
					'label'            => __( 'Project Wizard', 'coordina-project-wizard' ),
					'enabled'          => $can_access,
					'status'           => $can_access ? 'available' : 'restricted',
					'requires_license' => false,
					'source'           => 'project-wizard-addon',
					'reason'           => $can_access ? '' : __( 'Your role cannot run the Project Wizard.', 'coordina-project-wizard' ),
					'route'            => array(
						'page' => 'coordina-project-wizard',
					),
				),
			),
		);
	}
}