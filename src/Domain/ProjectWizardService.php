<?php
/**
 * Project Wizard application service.
 */

declare(strict_types=1);

namespace CoordinaProjectWizard\Domain;

use Coordina\Infrastructure\Persistence\MilestoneRepository;
use Coordina\Infrastructure\Persistence\RiskIssueRepository;
use Coordina\Platform\Contracts\ProjectRepositoryInterface;
use Coordina\Platform\Contracts\SettingsStoreInterface;
use Coordina\Platform\Contracts\TaskRepositoryInterface;
use CoordinaProjectWizard\Templates\ProjectTemplateCatalog;
use RuntimeException;
use WP_User;

final class ProjectWizardService {
	/**
	 * @var ProjectRepositoryInterface
	 */
	private $projects;

	/**
	 * @var TaskRepositoryInterface
	 */
	private $tasks;

	/**
	 * @var MilestoneRepository
	 */
	private $milestones;

	/**
	 * @var RiskIssueRepository
	 */
	private $risks_issues;

	/**
	 * @var SettingsStoreInterface
	 */
	private $settings;

	/**
	 * @var ProjectTemplateCatalog
	 */
	private $template_catalog;

	public function __construct( ProjectRepositoryInterface $projects, TaskRepositoryInterface $tasks, MilestoneRepository $milestones, RiskIssueRepository $risks_issues, SettingsStoreInterface $settings, ProjectTemplateCatalog $template_catalog ) {
		$this->projects         = $projects;
		$this->tasks            = $tasks;
		$this->milestones       = $milestones;
		$this->risks_issues     = $risks_issues;
		$this->settings         = $settings;
		$this->template_catalog = $template_catalog;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function bootstrap(): array {
		$settings  = $this->settings->get();
		$choices   = $this->settings->choice_lists();
		$templates = $this->template_catalog->all();

		return array(
			'settings'  => $this->wizard_settings( $settings ),
			'templates' => $templates,
			'users'     => $this->assignable_users(),
			'choices'   => array(
				'default_template'            => $choices['project_wizard.default_template'] ?? $this->template_catalog->keys(),
				'default_status'              => $choices['project_wizard.default_status'] ?? array( 'draft', 'planned', 'active' ),
				'default_visibility'          => $choices['project_wizard.default_visibility'] ?? array( 'team', 'private', 'public' ),
				'default_notification_policy' => $choices['project_wizard.default_notification_policy'] ?? array( 'default', 'important-only', 'all-updates', 'muted' ),
				'default_task_group_label'    => $choices['project_wizard.default_task_group_label'] ?? array( 'stage', 'phase', 'bucket' ),
			),
			'dropdowns' => array(
				'project_statuses'       => $this->project_statuses( $settings ),
				'task_statuses'          => $this->task_statuses( $settings ),
				'milestone_statuses'     => $this->milestone_statuses( $settings ),
				'risk_issue_statuses'    => $this->risk_issue_statuses( $settings ),
				'visibility_levels'      => $this->dropdown_list( $settings, 'visibilityLevels', array( 'team', 'private', 'public' ) ),
				'notification_policies'  => $this->dropdown_list( $settings, 'projectNotificationPolicies', array( 'default', 'important-only', 'all-updates', 'muted' ) ),
				'priorities'             => $this->dropdown_list( $settings, 'priorities', array( 'low', 'normal', 'high', 'urgent' ) ),
				'health'                 => $this->dropdown_list( $settings, 'health', array( 'neutral', 'good', 'at-risk', 'blocked' ) ),
				'severities'             => $this->dropdown_list( $settings, 'severities', array( 'low', 'medium', 'high', 'critical' ) ),
				'impacts'                => $this->dropdown_list( $settings, 'impacts', array( 'low', 'medium', 'high', 'critical' ) ),
				'likelihoods'            => $this->dropdown_list( $settings, 'likelihoods', array( 'low', 'medium', 'high', 'critical' ) ),
			),
		);
	}

	/**
	 * @param array<string, mixed>|null $payload
	 * @return array<string, mixed>
	 */
	public function update_settings( ?array $payload ): array {
		$current     = $this->wizard_settings( $this->settings->get() );
		$choice_sets = $this->settings->choice_lists();
		$payload     = is_array( $payload ) ? $payload : array();
		$template_keys = $this->template_catalog->keys();
		$default_key   = $this->template_catalog->default_key();

		$next = array(
			'default_template'              => $this->sanitize_choice( (string) ( $payload['default_template'] ?? $current['default_template'] ), $choice_sets['project_wizard.default_template'] ?? $template_keys, $current['default_template'] ?: $default_key ),
			'default_status'                => $this->sanitize_choice( (string) ( $payload['default_status'] ?? $current['default_status'] ), $choice_sets['project_wizard.default_status'] ?? array( 'draft', 'planned', 'active' ), $current['default_status'] ),
			'default_visibility'            => $this->sanitize_choice( (string) ( $payload['default_visibility'] ?? $current['default_visibility'] ), $choice_sets['project_wizard.default_visibility'] ?? array( 'team', 'private', 'public' ), $current['default_visibility'] ),
			'default_notification_policy'   => $this->sanitize_choice( (string) ( $payload['default_notification_policy'] ?? $current['default_notification_policy'] ), $choice_sets['project_wizard.default_notification_policy'] ?? array( 'default', 'important-only', 'all-updates', 'muted' ), $current['default_notification_policy'] ),
			'default_task_group_label'      => $this->sanitize_choice( (string) ( $payload['default_task_group_label'] ?? $current['default_task_group_label'] ), $choice_sets['project_wizard.default_task_group_label'] ?? array( 'stage', 'phase', 'bucket' ), $current['default_task_group_label'] ),
			'create_kickoff_task'           => ! empty( $payload['create_kickoff_task'] ),
			'auto_assign_manager_as_member' => ! empty( $payload['auto_assign_manager_as_member'] ),
		);

		$updated = $this->settings->update(
			array(
				'project_wizard' => $next,
			)
		);

		return $this->wizard_settings( $updated );
	}

	/**
	 * @param array<string, mixed>|null $payload
	 * @return array<string, mixed>
	 */
	public function create_project( ?array $payload ): array {
		$payload         = is_array( $payload ) ? $payload : array();
		$raw_settings    = $this->settings->get();
		$settings        = $this->wizard_settings( $raw_settings );
		$project_statuses = $this->project_statuses( $raw_settings );
		$template_keys   = $this->template_catalog->keys();

		$title = \sanitize_text_field( (string) ( $payload['title'] ?? '' ) );

		if ( '' === $title ) {
			throw new RuntimeException( \__( 'Project title is required.', 'coordina-project-wizard' ) );
		}

		$template_key = $this->sanitize_choice( (string) ( $payload['template_key'] ?? $settings['default_template'] ), $template_keys, $settings['default_template'] );
		$template     = $this->template_catalog->find( $template_key );

		if ( empty( $template ) ) {
			throw new RuntimeException( \__( 'A valid project template is required.', 'coordina-project-wizard' ) );
		}

		$status       = $this->sanitize_choice( (string) ( $payload['status'] ?? $settings['default_status'] ), $project_statuses, $settings['default_status'] );
		$visibility   = $this->sanitize_choice( (string) ( $payload['visibility'] ?? $settings['default_visibility'] ), $this->dropdown_list( $raw_settings, 'visibilityLevels', array( 'team', 'private', 'public' ) ), $settings['default_visibility'] );
		$notify       = $this->sanitize_choice( (string) ( $payload['notification_policy'] ?? $settings['default_notification_policy'] ), $this->dropdown_list( $raw_settings, 'projectNotificationPolicies', array( 'default', 'important-only', 'all-updates', 'muted' ) ), $settings['default_notification_policy'] );
		$group_label  = $this->sanitize_choice( (string) ( $payload['task_group_label'] ?? $settings['default_task_group_label'] ), array( 'stage', 'phase', 'bucket', '' ), $settings['default_task_group_label'] );

		$manager_id   = max( 0, (int) ( $payload['manager_user_id'] ?? \get_current_user_id() ) );
		$sponsor_id   = max( 0, (int) ( $payload['sponsor_user_id'] ?? 0 ) );
		$team_members = $this->parse_user_ids( $payload['team_member_ids'] ?? array() );

		if ( ! empty( $payload['auto_assign_manager_as_member'] ) || ( ! isset( $payload['auto_assign_manager_as_member'] ) && $settings['auto_assign_manager_as_member'] ) ) {
			if ( $manager_id > 0 && ! in_array( $manager_id, $team_members, true ) ) {
				$team_members[] = $manager_id;
			}
		}

		$project = $this->projects->create(
			array(
				'code'                => \sanitize_text_field( (string) ( $payload['code'] ?? '' ) ),
				'title'               => $title,
				'description'         => \wp_kses_post( (string) ( $payload['description'] ?? '' ) ),
				'status'              => $status,
				'health'              => $this->sanitize_choice( (string) ( $payload['health'] ?? 'neutral' ), $this->dropdown_list( $raw_settings, 'health', array( 'neutral', 'good', 'at-risk', 'blocked' ) ), 'neutral' ),
				'priority'            => $this->sanitize_choice( (string) ( $payload['priority'] ?? 'normal' ), $this->dropdown_list( $raw_settings, 'priorities', array( 'low', 'normal', 'high', 'urgent' ) ), 'normal' ),
				'manager_user_id'     => $manager_id,
				'sponsor_user_id'     => $sponsor_id,
				'visibility'          => $visibility,
				'notification_policy' => $notify,
				'task_group_label'    => $group_label,
				'start_date'          => $this->normalize_datetime( (string) ( $payload['start_date'] ?? '' ) ),
				'target_end_date'     => $this->normalize_datetime( (string) ( $payload['target_end_date'] ?? '' ) ),
			)
		);

		$project_id = (int) ( $project['id'] ?? 0 );

		if ( $project_id <= 0 ) {
			throw new RuntimeException( \__( 'Project could not be created.', 'coordina-project-wizard' ) );
		}

		if ( ! empty( $team_members ) ) {
			$this->projects->update_settings(
				$project_id,
				array(
					'team_member_ids' => $team_members,
				)
			);
		}

		$scaffold = $this->build_scaffold(
			$template,
			$project_id,
			$manager_id,
			(string) ( $project['start_date'] ?? '' ),
			! empty( $payload['create_kickoff_task'] ) || ( ! isset( $payload['create_kickoff_task'] ) && $settings['create_kickoff_task'] ),
			$this->sanitize_milestones( $payload['milestones'] ?? ( $template['milestones'] ?? array() ), $raw_settings ),
			$this->sanitize_tasks( $payload['tasks'] ?? ( $template['tasks'] ?? array() ), $raw_settings ),
			$this->sanitize_risks( $payload['risks'] ?? ( $template['risks'] ?? array() ), $raw_settings )
		);

		return array(
			'project'         => $project,
			'template'        => $template_key,
			'groupsCreated'   => $scaffold['groups_created'],
			'tasksCreated'    => $scaffold['tasks_created'],
			'milestonesCreated' => $scaffold['milestones_created'],
			'risksCreated'    => $scaffold['risks_created'],
			'teamMembers'     => count( $team_members ),
			'workspaceRoute'  => array(
				'page'       => 'coordina-projects',
				'project_id' => $project_id,
				'project_tab'=> 'overview',
			),
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function wizard_settings( array $settings ): array {
		$section = is_array( $settings['project_wizard'] ?? null ) ? $settings['project_wizard'] : array();
		$default_template = $this->template_catalog->default_key();

		return array(
			'default_template'              => $this->sanitize_choice( (string) ( $section['default_template'] ?? $default_template ), $this->template_catalog->keys(), $default_template ),
			'default_status'                => \sanitize_key( (string) ( $section['default_status'] ?? 'planned' ) ),
			'default_visibility'            => \sanitize_key( (string) ( $section['default_visibility'] ?? 'team' ) ),
			'default_notification_policy'   => \sanitize_key( (string) ( $section['default_notification_policy'] ?? 'default' ) ),
			'default_task_group_label'      => \sanitize_key( (string) ( $section['default_task_group_label'] ?? 'stage' ) ),
			'create_kickoff_task'           => ! empty( $section['create_kickoff_task'] ),
			'auto_assign_manager_as_member' => ! empty( $section['auto_assign_manager_as_member'] ),
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return string[]
	 */
	private function project_statuses( array $settings ): array {
		$dropdowns = is_array( $settings['dropdowns'] ?? null ) ? $settings['dropdowns'] : array();
		$statuses  = is_array( $dropdowns['statuses']['projects'] ?? null ) ? $dropdowns['statuses']['projects'] : array();
		$normalized = array_values(
			array_filter(
				array_map( 'sanitize_key', $statuses ),
				static function ( string $status ): bool {
					return '' !== $status && in_array( $status, array( 'draft', 'planned', 'active', 'on-hold', 'at-risk', 'blocked', 'completed', 'cancelled', 'archived' ), true );
				}
			)
		);

		return ! empty( $normalized ) ? $normalized : array( 'draft', 'planned', 'active' );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return string[]
	 */
	private function task_statuses( array $settings ): array {
		$dropdowns = is_array( $settings['dropdowns'] ?? null ) ? $settings['dropdowns'] : array();
		$statuses  = is_array( $dropdowns['statuses']['tasks'] ?? null ) ? $dropdowns['statuses']['tasks'] : array();
		$normalized = array_values( array_filter( array_map( 'sanitize_key', $statuses ) ) );

		return ! empty( $normalized ) ? $normalized : array( 'new', 'to-do', 'in-progress', 'waiting', 'blocked', 'in-review', 'done', 'cancelled' );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return string[]
	 */
	private function milestone_statuses( array $settings ): array {
		$dropdowns = is_array( $settings['dropdowns'] ?? null ) ? $settings['dropdowns'] : array();
		$statuses  = is_array( $dropdowns['statuses']['milestones'] ?? null ) ? $dropdowns['statuses']['milestones'] : array();
		$normalized = array_values( array_filter( array_map( 'sanitize_key', $statuses ) ) );

		return ! empty( $normalized ) ? $normalized : array( 'planned', 'in-progress', 'at-risk', 'completed', 'skipped' );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return string[]
	 */
	private function risk_issue_statuses( array $settings ): array {
		$dropdowns = is_array( $settings['dropdowns'] ?? null ) ? $settings['dropdowns'] : array();
		$statuses  = is_array( $dropdowns['statuses']['risksIssues'] ?? null ) ? $dropdowns['statuses']['risksIssues'] : array();
		$normalized = array_values( array_filter( array_map( 'sanitize_key', $statuses ) ) );

		return ! empty( $normalized ) ? $normalized : array( 'identified', 'monitoring', 'mitigation-in-progress', 'escalated', 'resolved', 'closed' );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return string[]
	 */
	private function dropdown_list( array $settings, string $key, array $fallback ): array {
		$dropdowns = is_array( $settings['dropdowns'] ?? null ) ? $settings['dropdowns'] : array();
		$values    = is_array( $dropdowns[ $key ] ?? null ) ? $dropdowns[ $key ] : $fallback;

		return array_values( array_filter( array_map( 'sanitize_key', $values ) ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function assignable_users(): array {
		$users = \get_users(
			array(
				'number' => 200,
			)
		);

		$filtered = array_values(
			array_filter(
				$users,
				static function ( $user ): bool {
					return $user instanceof WP_User && (
						\user_can( $user, 'coordina_run_project_wizard' )
						|| \user_can( $user, 'coordina_manage_projects' )
						|| \user_can( $user, 'coordina_access' )
					);
				}
			)
		);

		usort(
			$filtered,
			static function ( WP_User $left, WP_User $right ): int {
				return strcasecmp( (string) $left->display_name, (string) $right->display_name );
			}
		);

		return array_map(
			static function ( WP_User $user ): array {
				return array(
					'id'    => (int) $user->ID,
					'label' => (string) $user->display_name,
				);
			},
			array_slice( $filtered, 0, 100 )
		);
	}

	/**
	 * @return array<string, int>
	 */
	private function build_scaffold( array $template, int $project_id, int $manager_id, string $start_date, bool $create_kickoff_task, array $milestones, array $tasks, array $risks ): array {
		unset( $template );

		$group_ids          = array();
		$groups_created     = 0;
		$tasks_created      = 0;
		$milestones_created = 0;
		$risks_created      = 0;

		foreach ( $this->selected_phases( $tasks ) as $phase_title ) {
			$group = $this->tasks->create_group(
				$project_id,
				array(
					'title' => $phase_title,
				)
			);

			$group_ids[ $phase_title ] = (int) ( $group['id'] ?? 0 );
			$groups_created++;
		}

		foreach ( $tasks as $task ) {
			if ( empty( $task['selected'] ) ) {
				continue;
			}

			$this->tasks->create(
				array(
					'title'            => (string) $task['title'],
					'project_id'       => $project_id,
					'task_group_id'    => (int) ( $group_ids[ (string) ( $task['phase'] ?? '' ) ] ?? 0 ),
					'status'           => (string) ( $task['status'] ?? 'new' ),
					'priority'         => (string) ( $task['priority'] ?? 'normal' ),
					'assignee_user_id' => $manager_id,
					'start_date'       => $this->normalize_datetime( (string) ( $task['start_date'] ?? $start_date ) ),
					'due_date'         => $this->normalize_datetime( (string) ( $task['due_date'] ?? '' ) ),
				)
			);

			$tasks_created++;
		}

		foreach ( $milestones as $milestone ) {
			if ( empty( $milestone['selected'] ) ) {
				continue;
			}

			$this->milestones->create(
				array(
					'project_id'         => $project_id,
					'title'              => (string) $milestone['title'],
					'status'             => (string) ( $milestone['status'] ?? 'planned' ),
					'owner_user_id'      => $manager_id,
					'due_date'           => $this->normalize_datetime( (string) ( $milestone['due_date'] ?? '' ) ),
					'completion_percent' => (int) ( $milestone['completion_percent'] ?? 0 ),
					'dependency_flag'    => ! empty( $milestone['dependency_flag'] ),
					'notes'              => (string) ( $milestone['notes'] ?? '' ),
				)
			);

			$milestones_created++;
		}

		foreach ( $risks as $risk ) {
			if ( empty( $risk['selected'] ) ) {
				continue;
			}

			$this->risks_issues->create(
				array(
					'project_id'             => $project_id,
					'object_type'            => (string) ( $risk['object_type'] ?? 'risk' ),
					'title'                  => (string) $risk['title'],
					'description'            => (string) ( $risk['description'] ?? '' ),
					'status'                 => (string) ( $risk['status'] ?? 'identified' ),
					'severity'               => (string) ( $risk['severity'] ?? 'medium' ),
					'impact'                 => (string) ( $risk['impact'] ?? 'medium' ),
					'likelihood'             => (string) ( $risk['likelihood'] ?? 'medium' ),
					'owner_user_id'          => $manager_id,
					'mitigation_plan'        => (string) ( $risk['mitigation_plan'] ?? '' ),
					'target_resolution_date' => $this->normalize_datetime( (string) ( $risk['target_resolution_date'] ?? '' ) ),
				)
			);

			$risks_created++;
		}

		if ( $create_kickoff_task ) {
			$this->tasks->create(
				array(
					'title'            => \__( 'Confirm kickoff, owners, and first next actions', 'coordina-project-wizard' ),
					'project_id'       => $project_id,
					'task_group_id'    => 0,
					'status'           => 'new',
					'priority'         => 'high',
					'assignee_user_id' => $manager_id,
					'start_date'       => $this->normalize_datetime( $start_date ),
					'due_date'         => $this->shift_datetime( $start_date, 5 ),
				)
			);

			$tasks_created++;
		}

		return array(
			'groups_created'     => $groups_created,
			'tasks_created'      => $tasks_created,
			'milestones_created' => $milestones_created,
			'risks_created'      => $risks_created,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $tasks
	 * @return array<int, string>
	 */
	private function selected_phases( array $tasks ): array {
		$phases = array();

		foreach ( $tasks as $task ) {
			if ( empty( $task['selected'] ) ) {
				continue;
			}

			$phase = \sanitize_text_field( (string) ( $task['phase'] ?? '' ) );

			if ( '' === $phase || in_array( $phase, $phases, true ) ) {
				continue;
			}

			$phases[] = $phase;
		}

		return $phases;
	}

	/**
	 * @param mixed $items
	 * @param array<string, mixed> $settings
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_milestones( $items, array $settings ): array {
		$allowed_statuses = $this->milestone_statuses( $settings );
		$items            = is_array( $items ) ? $items : array();
		$clean            = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title = \sanitize_text_field( (string) ( $item['title'] ?? '' ) );

			if ( '' === $title ) {
				continue;
			}

			$clean[] = array(
				'key'                => \sanitize_key( (string) ( $item['key'] ?? $title ) ),
				'title'              => $title,
				'selected'           => ! empty( $item['selected'] ),
				'due_date'           => $this->normalize_datetime( (string) ( $item['due_date'] ?? '' ) ),
				'status'             => $this->sanitize_choice( (string) ( $item['status'] ?? 'planned' ), $allowed_statuses, 'planned' ),
				'completion_percent' => min( 100, max( 0, (int) ( $item['completion_percent'] ?? 0 ) ) ),
				'dependency_flag'    => ! empty( $item['dependency_flag'] ),
				'notes'              => \wp_kses_post( (string) ( $item['notes'] ?? '' ) ),
			);
		}

		return $clean;
	}

	/**
	 * @param mixed $items
	 * @param array<string, mixed> $settings
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_tasks( $items, array $settings ): array {
		$allowed_statuses   = $this->task_statuses( $settings );
		$allowed_priorities = $this->dropdown_list( $settings, 'priorities', array( 'low', 'normal', 'high', 'urgent' ) );
		$items              = is_array( $items ) ? $items : array();
		$clean              = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title = \sanitize_text_field( (string) ( $item['title'] ?? '' ) );

			if ( '' === $title ) {
				continue;
			}

			$phase = \sanitize_text_field( (string) ( $item['phase'] ?? '' ) );

			$clean[] = array(
				'key'        => \sanitize_key( (string) ( $item['key'] ?? $title ) ),
				'title'      => $title,
				'phase'      => $phase,
				'phase_key'  => \sanitize_key( (string) ( $item['phase_key'] ?? $phase ) ),
				'selected'   => ! empty( $item['selected'] ),
				'priority'   => $this->sanitize_choice( (string) ( $item['priority'] ?? 'normal' ), $allowed_priorities, 'normal' ),
				'status'     => $this->sanitize_choice( (string) ( $item['status'] ?? 'new' ), $allowed_statuses, 'new' ),
				'start_date' => $this->normalize_datetime( (string) ( $item['start_date'] ?? '' ) ),
				'due_date'   => $this->normalize_datetime( (string) ( $item['due_date'] ?? '' ) ),
			);
		}

		return $clean;
	}

	/**
	 * @param mixed $items
	 * @param array<string, mixed> $settings
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_risks( $items, array $settings ): array {
		$allowed_statuses    = $this->risk_issue_statuses( $settings );
		$allowed_severities  = $this->dropdown_list( $settings, 'severities', array( 'low', 'medium', 'high', 'critical' ) );
		$allowed_impacts     = $this->dropdown_list( $settings, 'impacts', array( 'low', 'medium', 'high', 'critical' ) );
		$allowed_likelihoods = $this->dropdown_list( $settings, 'likelihoods', array( 'low', 'medium', 'high', 'critical' ) );
		$items               = is_array( $items ) ? $items : array();
		$clean               = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title = \sanitize_text_field( (string) ( $item['title'] ?? '' ) );

			if ( '' === $title ) {
				continue;
			}

			$clean[] = array(
				'key'                    => \sanitize_key( (string) ( $item['key'] ?? $title ) ),
				'title'                  => $title,
				'object_type'            => in_array( \sanitize_key( (string) ( $item['object_type'] ?? 'risk' ) ), array( 'risk', 'issue' ), true ) ? \sanitize_key( (string) ( $item['object_type'] ?? 'risk' ) ) : 'risk',
				'selected'               => ! empty( $item['selected'] ),
				'description'            => \wp_kses_post( (string) ( $item['description'] ?? '' ) ),
				'status'                 => $this->sanitize_choice( (string) ( $item['status'] ?? 'identified' ), $allowed_statuses, 'identified' ),
				'severity'               => $this->sanitize_choice( (string) ( $item['severity'] ?? 'medium' ), $allowed_severities, 'medium' ),
				'impact'                 => $this->sanitize_choice( (string) ( $item['impact'] ?? 'medium' ), $allowed_impacts, 'medium' ),
				'likelihood'             => $this->sanitize_choice( (string) ( $item['likelihood'] ?? 'medium' ), $allowed_likelihoods, 'medium' ),
				'mitigation_plan'        => \wp_kses_post( (string) ( $item['mitigation_plan'] ?? '' ) ),
				'target_resolution_date' => $this->normalize_datetime( (string) ( $item['target_resolution_date'] ?? '' ) ),
			);
		}

		return $clean;
	}

	/**
	 * @param mixed $value
	 * @return int[]
	 */
	private function parse_user_ids( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\r\n,]+/', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'intval', $value ) ) ) );
	}

	private function sanitize_choice( string $value, array $allowed, string $fallback ): string {
		$value = \sanitize_key( $value );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	private function normalize_datetime( string $value ): ?string {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	private function shift_datetime( string $value, int $days ): ?string {
		$normalized = $this->normalize_datetime( $value );

		if ( null === $normalized ) {
			return null;
		}

		$timestamp = strtotime( $normalized . ' +' . $days . ' days UTC' );

		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}