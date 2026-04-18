<?php
/**
 * Registers the Project Wizard add-on with Coordina.
 */

declare(strict_types=1);

namespace CoordinaProjectWizard\Providers;

use Coordina\Core\Container;
use Coordina\Infrastructure\Persistence\MilestoneRepository;
use Coordina\Infrastructure\Persistence\RiskIssueRepository;
use Coordina\Platform\Contracts\ProjectRepositoryInterface;
use Coordina\Platform\Contracts\ServiceProvider;
use Coordina\Platform\Contracts\SettingsStoreInterface;
use Coordina\Platform\Contracts\TaskRepositoryInterface;
use Coordina\Platform\Registry\AdminPageRegistry;
use Coordina\Platform\Registry\CapabilityRegistry;
use Coordina\Platform\Registry\RestRouteRegistry;
use Coordina\Platform\Registry\SettingsRegistry;
use CoordinaProjectWizard\Admin\WizardAssets;
use CoordinaProjectWizard\Domain\ProjectWizardService;
use CoordinaProjectWizard\Entitlements\ProjectWizardEntitlementProvider;
use CoordinaProjectWizard\Rest\WizardRoutes;
use CoordinaProjectWizard\Templates\ProjectTemplateCatalog;

final class ProjectWizardServiceProvider implements ServiceProvider {
	public function register( Container $container ): void {
		$container->set(
			'project_wizard.template_catalog',
			static function (): ProjectTemplateCatalog {
				return new ProjectTemplateCatalog( COORDINA_PROJECT_WIZARD_PATH . 'config/project-template-catalog.json' );
			}
		);

		$container->set(
			'project_wizard.service',
			static function ( Container $container ): ProjectWizardService {
				return new ProjectWizardService(
					$container->get( ProjectRepositoryInterface::class ),
					$container->get( TaskRepositoryInterface::class ),
					$container->get( 'milestones' ),
					$container->get( 'risks_issues' ),
					$container->get( SettingsStoreInterface::class ),
					$container->get( 'project_wizard.template_catalog' )
				);
			}
		);

		$container->set(
			'project_wizard.assets',
			static function (): WizardAssets {
				return new WizardAssets();
			}
		);

		$container->set(
			'project_wizard.entitlement_provider',
			static function (): ProjectWizardEntitlementProvider {
				return new ProjectWizardEntitlementProvider();
			}
		);

		$container->extend(
			'admin_pages',
			static function ( AdminPageRegistry $registry ): AdminPageRegistry {
				$registry->add(
					'coordina-project-wizard',
					array(
						'title'               => \__( 'Project Wizard', 'coordina-project-wizard' ),
						'menu_title'          => \__( 'Project Wizard', 'coordina-project-wizard' ),
						'capability'          => 'coordina_run_project_wizard',
						'description'         => \__( 'Create a new project with ownership, structure, and starter work in one guided flow.', 'coordina-project-wizard' ),
						'priority'            => 'secondary',
						'purpose'             => 'execution',
						'hidden'              => true,
						'non_admin_visible'   => true,
						'allow_direct_access' => true,
					)
				);

				return $registry;
			}
		);

		$container->extend(
			'rest_routes',
			static function ( RestRouteRegistry $registry, Container $container ): RestRouteRegistry {
				$registry->add(
					static function ( $registrar ) use ( $container ): void {
						unset( $registrar );
						WizardRoutes::register( $container->get( 'project_wizard.service' ) );
					}
				);

				return $registry;
			}
		);

		$container->extend(
			'settings_registry',
			static function ( SettingsRegistry $registry, Container $container ): SettingsRegistry {
				$template_catalog = $container->get( 'project_wizard.template_catalog' );
				$template_keys    = $template_catalog instanceof ProjectTemplateCatalog ? $template_catalog->keys() : array();
				$default_template = $template_catalog instanceof ProjectTemplateCatalog ? $template_catalog->default_key() : '';

				$registry->add_defaults(
					array(
						'project_wizard' => array(
							'default_template'              => $default_template,
							'default_status'                => 'planned',
							'default_visibility'            => 'team',
							'default_notification_policy'   => 'default',
							'default_task_group_label'      => 'stage',
							'create_kickoff_task'           => true,
							'auto_assign_manager_as_member' => true,
						),
					)
				);

				$registry->add_choices( 'project_wizard.default_template', $template_keys );
				$registry->add_choices( 'project_wizard.default_status', array( 'draft', 'planned', 'active' ) );
				$registry->add_choices( 'project_wizard.default_visibility', array( 'team', 'private', 'public' ) );
				$registry->add_choices( 'project_wizard.default_notification_policy', array( 'default', 'important-only', 'all-updates', 'muted' ) );
				$registry->add_choices( 'project_wizard.default_task_group_label', array( 'stage', 'phase', 'bucket' ) );

				return $registry;
			}
		);

		$container->extend(
			'capability_registry',
			static function ( CapabilityRegistry $registry ): CapabilityRegistry {
				$labels = $registry->labels();
				$caps   = $registry->role_capabilities();

				foreach ( array( 'administrator', 'coordina_project_manager' ) as $role ) {
					$current_caps = $caps[ $role ] ?? array();
					$current_caps[] = 'coordina_run_project_wizard';

					$registry->add_role(
						$role,
						$labels[ $role ] ?? ucwords( str_replace( '_', ' ', $role ) ),
						array_values( array_unique( $current_caps ) )
					);
				}

				return $registry;
			}
		);
	}

	public function boot( Container $container ): void {
		$container->get( 'project_wizard.assets' )->register();
	}
}