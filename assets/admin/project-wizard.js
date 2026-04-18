(function () {
const app = window.CoordinaAdminApp || {};

if (!app.root || !app.state || app.state.page !== 'coordina-project-wizard') {
	return;
}

const { __, escapeHtml, nice, dateLabel, dateTimeInputValue, api, notify, openRoute } = app;
const PAGE_KEY = 'coordina-project-wizard';
const root = app.root;
const steps = [
	{ key: 'template', label: __('Template', 'coordina-project-wizard') },
	{ key: 'core', label: __('Core fields', 'coordina-project-wizard') },
	{ key: 'milestones', label: __('Milestones', 'coordina-project-wizard') },
	{ key: 'work', label: __('Tasks and risks', 'coordina-project-wizard') },
	{ key: 'review', label: __('Overview', 'coordina-project-wizard') },
];

const originalRender = typeof app.render === 'function' ? app.render.bind(app) : function () {};
const originalClick = typeof app.handleAdminClickAction === 'function' ? app.handleAdminClickAction.bind(app) : async function () {};
const originalSubmit = typeof app.handleAdminSubmitEvent === 'function' ? app.handleAdminSubmitEvent.bind(app) : async function () {};

app.state.projectWizard = Object.assign({
	loading: true,
	loaded: false,
	bootstrap: null,
	form: null,
	settingsDraft: null,
	created: null,
	isSavingSettings: false,
	isCreating: false,
	step: 1,
	listenersBound: false,
}, app.state.projectWizard || {});

function wizardState() {
	return app.state.projectWizard;
}

function clone(value) {
	return JSON.parse(JSON.stringify(value || null));
}

function templates() {
	const bootstrap = wizardState().bootstrap || {};
	return Array.isArray(bootstrap.templates) ? bootstrap.templates : [];
}

function templateChoices() {
	return templates().map((template) => String(template.key || '')).filter(Boolean);
}

function selectedTemplateKey() {
	const wizard = wizardState();
	const defaults = wizard.bootstrap && wizard.bootstrap.settings ? wizard.bootstrap.settings : {};
	const preferred = String((wizard.form && wizard.form.template_key) || defaults.default_template || templateChoices()[0] || '');
	return templateChoices().includes(preferred) ? preferred : (templateChoices()[0] || '');
}

function selectedTemplate() {
	const key = selectedTemplateKey();
	return templates().find((template) => String(template.key || '') === key) || null;
}

function templatePrefill(template) {
	return {
		title: String((template && template.title) || ''),
		description: String((template && (template.description || template.wizard_summary)) || ''),
	};
}

function buildInitialForm(data) {
	const defaults = data && data.settings ? data.settings : {};
	const availableTemplates = Array.isArray(data && data.templates) ? data.templates : [];
	const templateKey = String(defaults.default_template || (availableTemplates[0] && availableTemplates[0].key) || '');
	const template = availableTemplates.find((item) => String(item.key || '') === templateKey) || availableTemplates[0] || {};
	const prefill = templatePrefill(template);
	return {
		title: prefill.title,
		code: '',
		description: prefill.description,
		status: defaults.default_status || 'planned',
		priority: 'normal',
		health: 'neutral',
		manager_user_id: '',
		sponsor_user_id: '',
		team_member_ids: [],
		start_date: '',
		target_end_date: '',
		visibility: defaults.default_visibility || 'team',
		notification_policy: defaults.default_notification_policy || 'default',
		task_group_label: defaults.default_task_group_label || 'stage',
		template_key: templateKey,
		create_kickoff_task: defaults.create_kickoff_task !== false,
		auto_assign_manager_as_member: defaults.auto_assign_manager_as_member !== false,
		milestones: clone(template.milestones || []) || [],
		tasks: clone(template.tasks || []) || [],
		risks: clone(template.risks || []) || [],
	};
}

function buildSettingsDraft(data) {
	const defaults = data && data.settings ? data.settings : {};
	return {
		default_template: defaults.default_template || templateChoices()[0] || '',
		default_status: defaults.default_status || 'planned',
		default_visibility: defaults.default_visibility || 'team',
		default_notification_policy: defaults.default_notification_policy || 'default',
		default_task_group_label: defaults.default_task_group_label || 'stage',
		create_kickoff_task: defaults.create_kickoff_task !== false,
		auto_assign_manager_as_member: defaults.auto_assign_manager_as_member !== false,
	};
	}

function currentStep() {
	return Math.max(1, Math.min(steps.length, Number(wizardState().step || 1)));
}

function optionList(values, selected) {
	return (Array.isArray(values) ? values : []).map((value) => `<option value="${escapeHtml(value)}" ${String(selected || '') === String(value) ? 'selected' : ''}>${escapeHtml(nice(value))}</option>`).join('');
}

function userOptions(users, selectedValues) {
	const selected = Array.isArray(selectedValues) ? selectedValues.map((value) => Number(value)) : [];
	return (Array.isArray(users) ? users : []).map((user) => `<option value="${Number(user.id || 0)}" ${selected.includes(Number(user.id || 0)) ? 'selected' : ''}>${escapeHtml(user.label || '')}</option>`).join('');
}

function selectedItems(items) {
	return (Array.isArray(items) ? items : []).filter((item) => item && item.selected !== false);
}

function findUserLabel(userId) {
	const users = wizardState().bootstrap && Array.isArray(wizardState().bootstrap.users) ? wizardState().bootstrap.users : [];
	const match = users.find((user) => Number(user.id || 0) === Number(userId || 0));
	return match ? String(match.label || '') : '';
}

function applyTemplate(templateKey) {
	const wizard = wizardState();
	const previousTemplate = selectedTemplate();
	const template = templates().find((item) => String(item.key || '') === String(templateKey || ''));
	if (!wizard.form || !template) {
		return;
	}
	const previousPrefill = templatePrefill(previousTemplate);
	const nextPrefill = templatePrefill(template);
	wizard.form.template_key = String(template.key || '');
	if (!String(wizard.form.title || '').trim() || String(wizard.form.title || '') === previousPrefill.title) {
		wizard.form.title = nextPrefill.title;
	}
	if (!String(wizard.form.description || '').trim() || String(wizard.form.description || '') === previousPrefill.description) {
		wizard.form.description = nextPrefill.description;
	}
	wizard.form.milestones = clone(template.milestones || []) || [];
	wizard.form.tasks = clone(template.tasks || []) || [];
	wizard.form.risks = clone(template.risks || []) || [];
	wizard.created = null;
}

function defaultArrayItem(arrayName) {
	if (arrayName === 'milestones') {
		return { key: `custom-${Date.now()}`, title: '', selected: true, due_date: '', status: 'planned', dependency_flag: false, notes: '' };
	}
	if (arrayName === 'tasks') {
		const template = selectedTemplate();
		const firstPhase = template && Array.isArray(template.phases) && template.phases[0] ? String(template.phases[0].title || '') : '';
		return { key: `custom-${Date.now()}`, title: '', phase: firstPhase, phase_key: '', selected: true, priority: 'normal', status: 'new', start_date: '', due_date: '' };
	}
	return { key: `custom-${Date.now()}`, title: '', object_type: 'risk', selected: true, description: '', status: 'identified', severity: 'medium', impact: 'medium', likelihood: 'medium', mitigation_plan: '', target_resolution_date: '' };
}

function updateStateFromField(element) {
	const wizard = wizardState();
	if (!wizard.form || !element) {
		return;
	}
	const field = element.dataset.projectWizardField;
	if (!field) {
		return;
	}
	let value;
	if (element.tagName === 'SELECT' && element.multiple) {
		value = Array.from(element.selectedOptions).map((option) => Number(option.value));
	} else if (element.type === 'checkbox') {
		value = !!element.checked;
	} else if (element.type === 'number') {
		value = element.value === '' ? 0 : Number(element.value);
	} else {
		value = element.value;
	}
	const arrayName = element.dataset.projectWizardArray;
	if (arrayName) {
		const index = Number(element.dataset.projectWizardIndex || -1);
		if (!Array.isArray(wizard.form[arrayName]) || index < 0 || !wizard.form[arrayName][index]) {
			return;
		}
		wizard.form[arrayName][index][field] = value;
		return;
	}
	wizard.form[field] = value;
	if (field === 'template_key') {
		applyTemplate(value);
	}
}

function bindListeners() {
	const wizard = wizardState();
	if (wizard.listenersBound) {
		return;
	}
	root.addEventListener('input', (event) => {
		const element = event.target;
		if (!(element instanceof HTMLElement)) {
			return;
		}
		updateStateFromField(element);
	});
	root.addEventListener('change', (event) => {
		const element = event.target;
		if (!(element instanceof HTMLElement)) {
			return;
		}
		updateStateFromField(element);
	});
	wizard.listenersBound = true;
}

function stepIsValid(step) {
	const form = wizardState().form || {};
	if (step === 1 && !String(form.template_key || '')) {
		return __('Select a project template before continuing.', 'coordina-project-wizard');
	}
	if (step === 2 && !String(form.title || '').trim()) {
		return __('Project title is required before continuing.', 'coordina-project-wizard');
	}
	return '';
}

function moveStep(direction) {
	const wizard = wizardState();
	const current = currentStep();
	if (direction > 0) {
		const error = stepIsValid(current);
		if (error) {
			notify('error', error);
			return;
		}
	}
	wizard.step = Math.max(1, Math.min(steps.length, current + direction));
	app.render();
}

function resetWizard() {
	const wizard = wizardState();
	wizard.created = null;
	wizard.step = 1;
	wizard.form = buildInitialForm(wizard.bootstrap || {});
	app.render();
}

function templatePreview(template) {
	if (!template) {
		return `<section class="coordina-card coordina-project-wizard-preview"><p class="coordina-empty-inline">${escapeHtml(__('Select a template to see its structure.', 'coordina-project-wizard'))}</p></section>`;
	}
	const summary = template.summary || {};
	const phases = Array.isArray(template.phases) ? template.phases : [];
	const milestones = Array.isArray(template.milestones) ? template.milestones.slice(0, 4) : [];
	const risks = Array.isArray(template.risks) ? template.risks.slice(0, 4) : [];
	return `<section class="coordina-card coordina-project-wizard-preview"><div class="coordina-section-header"><div><h3>${escapeHtml(template.title || __('Template details', 'coordina-project-wizard'))}</h3><p class="coordina-section-note">${escapeHtml(template.description || '')}</p></div></div><p class="coordina-project-wizard-template-summary">${escapeHtml(template.wizard_summary || '')}</p><div class="coordina-summary-row coordina-summary-row--subtle"><span class="coordina-summary-chip"><strong>${Number(summary.phase_count || phases.length || 0)}</strong>${escapeHtml(__('Phases', 'coordina-project-wizard'))}</span><span class="coordina-summary-chip"><strong>${Number(summary.milestone_count || 0)}</strong>${escapeHtml(__('Milestones', 'coordina-project-wizard'))}</span><span class="coordina-summary-chip"><strong>${Number(summary.task_count || 0)}</strong>${escapeHtml(__('Tasks', 'coordina-project-wizard'))}</span><span class="coordina-summary-chip"><strong>${Number(summary.risk_count || 0)}</strong>${escapeHtml(__('Risks', 'coordina-project-wizard'))}</span></div><div class="coordina-project-wizard-preview-grid"><div><h4>${escapeHtml(__('Phase flow', 'coordina-project-wizard'))}</h4><ul class="coordina-work-list coordina-work-list--compact">${phases.map((phase) => `<li>${escapeHtml(phase.title || '')}</li>`).join('')}</ul></div><div><h4>${escapeHtml(__('Key milestones', 'coordina-project-wizard'))}</h4><ul class="coordina-work-list coordina-work-list--compact">${milestones.map((milestone) => `<li>${escapeHtml(milestone.title || '')}</li>`).join('')}</ul></div><div><h4>${escapeHtml(__('Known risks', 'coordina-project-wizard'))}</h4><ul class="coordina-work-list coordina-work-list--compact">${risks.map((risk) => `<li>${escapeHtml(risk.title || '')}</li>`).join('')}</ul></div></div></section>`;
}

function selectionSummaryCard() {
	const wizard = wizardState();
	const form = wizard.form || {};
	const template = selectedTemplate();
	const selectedMilestoneCount = selectedItems(form.milestones).length;
	const selectedTaskCount = selectedItems(form.tasks).length + (form.create_kickoff_task ? 1 : 0);
	const selectedRiskCount = selectedItems(form.risks).length;
	return `<section class="coordina-card coordina-project-wizard-aside-card"><div class="coordina-section-header"><div><h3>${escapeHtml(__('Current plan', 'coordina-project-wizard'))}</h3><p class="coordina-section-note">${escapeHtml(__('The wizard keeps your selections in memory as you move between steps.', 'coordina-project-wizard'))}</p></div></div><div class="coordina-project-wizard-summary-stack"><div><strong>${escapeHtml(__('Template', 'coordina-project-wizard'))}</strong><span>${escapeHtml((template && template.title) || __('Not selected yet', 'coordina-project-wizard'))}</span></div><div><strong>${escapeHtml(__('Project title', 'coordina-project-wizard'))}</strong><span>${escapeHtml(form.title || __('Not set yet', 'coordina-project-wizard'))}</span></div><div><strong>${escapeHtml(__('Project manager', 'coordina-project-wizard'))}</strong><span>${escapeHtml(findUserLabel(form.manager_user_id) || __('Defaults to the current user if left blank', 'coordina-project-wizard'))}</span></div></div><div class="coordina-summary-row coordina-summary-row--subtle"><span class="coordina-summary-chip"><strong>${selectedMilestoneCount}</strong>${escapeHtml(__('Milestones', 'coordina-project-wizard'))}</span><span class="coordina-summary-chip"><strong>${selectedTaskCount}</strong>${escapeHtml(__('Tasks', 'coordina-project-wizard'))}</span><span class="coordina-summary-chip"><strong>${selectedRiskCount}</strong>${escapeHtml(__('Risks', 'coordina-project-wizard'))}</span></div><div class="coordina-inline-actions"><button type="button" class="button" data-action="project-wizard-start-over">${escapeHtml(__('Start over', 'coordina-project-wizard'))}</button></div></section>`;
}

function defaultsCard() {
	const wizard = wizardState();
	const draft = wizard.settingsDraft || buildSettingsDraft(wizard.bootstrap || {});
	const choices = wizard.bootstrap && wizard.bootstrap.choices ? wizard.bootstrap.choices : {};
	return `<section class="coordina-card coordina-project-wizard-aside-card"><div class="coordina-section-header"><div><h3>${escapeHtml(__('Wizard defaults', 'coordina-project-wizard'))}</h3><p class="coordina-section-note">${escapeHtml(__('Keep the add-on defaults here so new projects start from your preferred baseline.', 'coordina-project-wizard'))}</p></div></div><form class="coordina-form" data-action="project-wizard-settings"><div class="coordina-form-grid"><label><span>${escapeHtml(__('Default template', 'coordina-project-wizard'))}</span><select name="default_template">${optionList(choices.default_template || [], draft.default_template)}</select></label><label><span>${escapeHtml(__('Default status', 'coordina-project-wizard'))}</span><select name="default_status">${optionList(choices.default_status || [], draft.default_status)}</select></label><label><span>${escapeHtml(__('Default visibility', 'coordina-project-wizard'))}</span><select name="default_visibility">${optionList(choices.default_visibility || [], draft.default_visibility)}</select></label><label><span>${escapeHtml(__('Default notification policy', 'coordina-project-wizard'))}</span><select name="default_notification_policy">${optionList(choices.default_notification_policy || [], draft.default_notification_policy)}</select></label><label><span>${escapeHtml(__('Default group label', 'coordina-project-wizard'))}</span><select name="default_task_group_label">${optionList(choices.default_task_group_label || [], draft.default_task_group_label)}</select></label><label class="coordina-checkbox"><input type="checkbox" name="create_kickoff_task" value="1" ${draft.create_kickoff_task ? 'checked' : ''} /><span>${escapeHtml(__('Enable kickoff task by default', 'coordina-project-wizard'))}</span></label><label class="coordina-checkbox"><input type="checkbox" name="auto_assign_manager_as_member" value="1" ${draft.auto_assign_manager_as_member ? 'checked' : ''} /><span>${escapeHtml(__('Add manager as member by default', 'coordina-project-wizard'))}</span></label></div><div class="coordina-form-actions"><button type="submit" class="button" ${wizard.isSavingSettings ? 'disabled' : ''}>${escapeHtml(wizard.isSavingSettings ? __('Saving...', 'coordina-project-wizard') : __('Save defaults', 'coordina-project-wizard'))}</button></div></form></section>`;
}

function formSection(title, note, content, fullWidth) {
	return `<section class="coordina-project-wizard-section ${fullWidth ? 'coordina-project-wizard-section--full' : ''}"><div class="coordina-project-wizard-section__header"><h4>${escapeHtml(title)}</h4><p>${escapeHtml(note)}</p></div>${content}</section>`;
}

function renderSummaryLine(label, value) {
	return `<div><strong>${escapeHtml(label)}</strong><span>${escapeHtml(value)}</span></div>`;
}

function renderReviewSection(title, note, items) {
	if (!items.length) {
		return `<section class="coordina-project-wizard-review-section"><h4>${escapeHtml(title)}</h4><p class="coordina-section-note">${escapeHtml(note)}</p><p class="coordina-empty-inline">${escapeHtml(__('None selected', 'coordina-project-wizard'))}</p></section>`;
	}
	return `<section class="coordina-project-wizard-review-section"><h4>${escapeHtml(title)}</h4><p class="coordina-section-note">${escapeHtml(note)}</p><ul class="coordina-project-wizard-review-bullets">${items.join('')}</ul></section>`;
}

function stepTemplate() {
	const wizard = wizardState();
	const form = wizard.form || {};
	const cards = templates().map((template) => {
		const summary = template.summary || {};
		const isSelected = String(form.template_key || '') === String(template.key || '');
		return `<button type="button" class="coordina-project-wizard-template ${isSelected ? 'is-selected' : ''}" data-action="project-wizard-select-template" data-template-key="${escapeHtml(template.key || '')}"><span class="coordina-project-wizard-template__body"><strong>${escapeHtml(template.title || '')}</strong><span>${escapeHtml(template.description || '')}</span><span class="coordina-project-wizard-template__meta">${escapeHtml(`${Number(summary.phase_count || 0)} ${__('phases', 'coordina-project-wizard')} • ${Number(summary.task_count || 0)} ${__('tasks', 'coordina-project-wizard')} • ${Number(summary.risk_count || 0)} ${__('risks', 'coordina-project-wizard')}`)}</span></span></button>`;
	}).join('');
	return `<section class="coordina-card coordina-card--wide"><div class="coordina-section-header"><div><h3>${escapeHtml(__('Select a project template', 'coordina-project-wizard'))}</h3><p class="coordina-section-note">${escapeHtml(__('Start with a template from the shared catalog, then tailor milestones, tasks, and risks before the project is created.', 'coordina-project-wizard'))}</p></div></div><div class="coordina-project-wizard-template-grid">${cards}</div></section>${templatePreview(selectedTemplate())}`;
}

function stepCore() {
	const wizard = wizardState();
	const form = wizard.form || {};
	const data = wizard.bootstrap || { users: [], dropdowns: {}, choices: {} };
	const dropdowns = data.dropdowns || {};
	const identity = `<div class="coordina-form-grid"><label><span>${escapeHtml(__('Project title', 'coordina-project-wizard'))}</span><input type="text" value="${escapeHtml(form.title || '')}" data-project-wizard-field="title" required /></label><label><span>${escapeHtml(__('Project code', 'coordina-project-wizard'))}</span><input type="text" value="${escapeHtml(form.code || '')}" data-project-wizard-field="code" /></label><label class="coordina-form-grid__full"><span>${escapeHtml(__('Description', 'coordina-project-wizard'))}</span><textarea rows="4" data-project-wizard-field="description">${escapeHtml(form.description || '')}</textarea></label></div>`;
	const ownership = `<div class="coordina-form-grid"><label><span>${escapeHtml(__('Project manager', 'coordina-project-wizard'))}</span><select data-project-wizard-field="manager_user_id"><option value="">${escapeHtml(__('Current user', 'coordina-project-wizard'))}</option>${userOptions(data.users, [form.manager_user_id])}</select></label><label><span>${escapeHtml(__('Sponsor', 'coordina-project-wizard'))}</span><select data-project-wizard-field="sponsor_user_id"><option value="">${escapeHtml(__('Optional', 'coordina-project-wizard'))}</option>${userOptions(data.users, [form.sponsor_user_id])}</select></label><label class="coordina-form-grid__full"><span>${escapeHtml(__('Team members', 'coordina-project-wizard'))}</span><select multiple size="6" data-project-wizard-field="team_member_ids">${userOptions(data.users, form.team_member_ids || [])}</select><small>${escapeHtml(__('Use Ctrl or Cmd to select multiple people.', 'coordina-project-wizard'))}</small></label></div>`;
	const planning = `<div class="coordina-form-grid"><label><span>${escapeHtml(__('Status', 'coordina-project-wizard'))}</span><select data-project-wizard-field="status">${optionList(dropdowns.project_statuses || [], form.status)}</select></label><label><span>${escapeHtml(__('Priority', 'coordina-project-wizard'))}</span><select data-project-wizard-field="priority">${optionList(dropdowns.priorities || [], form.priority)}</select></label><label><span>${escapeHtml(__('Health', 'coordina-project-wizard'))}</span><select data-project-wizard-field="health">${optionList(dropdowns.health || [], form.health)}</select></label><label><span>${escapeHtml(__('Start date', 'coordina-project-wizard'))}</span><input type="datetime-local" value="${escapeHtml(dateTimeInputValue(form.start_date || ''))}" data-project-wizard-field="start_date" /></label><label><span>${escapeHtml(__('Target end date', 'coordina-project-wizard'))}</span><input type="datetime-local" value="${escapeHtml(dateTimeInputValue(form.target_end_date || ''))}" data-project-wizard-field="target_end_date" /></label></div>`;
	const governance = `<div class="coordina-form-grid"><label><span>${escapeHtml(__('Visibility', 'coordina-project-wizard'))}</span><select data-project-wizard-field="visibility">${optionList(dropdowns.visibility_levels || [], form.visibility)}</select></label><label><span>${escapeHtml(__('Notification policy', 'coordina-project-wizard'))}</span><select data-project-wizard-field="notification_policy">${optionList(dropdowns.notification_policies || [], form.notification_policy)}</select></label><label><span>${escapeHtml(__('Task group label', 'coordina-project-wizard'))}</span><select data-project-wizard-field="task_group_label">${optionList((wizard.bootstrap && wizard.bootstrap.choices && wizard.bootstrap.choices.default_task_group_label) || [], form.task_group_label)}</select></label><div class="coordina-project-wizard-toggle-list coordina-form-grid__full"><label class="coordina-checkbox"><input type="checkbox" data-project-wizard-field="create_kickoff_task" ${form.create_kickoff_task ? 'checked' : ''} /><span>${escapeHtml(__('Include a kickoff task after the selected template tasks are created', 'coordina-project-wizard'))}</span></label><label class="coordina-checkbox"><input type="checkbox" data-project-wizard-field="auto_assign_manager_as_member" ${form.auto_assign_manager_as_member ? 'checked' : ''} /><span>${escapeHtml(__('Add the project manager to the project team automatically', 'coordina-project-wizard'))}</span></label></div></div>`;
	return `<section class="coordina-card coordina-card--wide coordina-form coordina-project-wizard-form"><div class="coordina-section-header"><div><h3>${escapeHtml(__('Core project fields', 'coordina-project-wizard'))}</h3><p class="coordina-section-note">${escapeHtml(__('Set the project identity, ownership, timeline, and governance fields that should exist before the scaffold is created.', 'coordina-project-wizard'))}</p></div></div><div class="coordina-project-wizard-sections">${formSection(__('Project identity', 'coordina-project-wizard'), __('Start with the essentials people will use to recognize the project.', 'coordina-project-wizard'), identity, false)}${formSection(__('Ownership and team', 'coordina-project-wizard'), __('Choose who owns the project and who should be included from day one.', 'coordina-project-wizard'), ownership, false)}${formSection(__('Planning baseline', 'coordina-project-wizard'), __('Set the planning signals and target dates before adding the scaffold.', 'coordina-project-wizard'), planning, false)}${formSection(__('Access and automation', 'coordina-project-wizard'), __('Control visibility, notifications, and automatic setup behavior.', 'coordina-project-wizard'), governance, false)}</div></section>`;
}

function milestoneRows() {
	const wizard = wizardState();
	const dropdowns = wizard.bootstrap && wizard.bootstrap.dropdowns ? wizard.bootstrap.dropdowns : {};
	const milestones = Array.isArray(wizard.form && wizard.form.milestones) ? wizard.form.milestones : [];
	if (!milestones.length) {
		return `<p class="coordina-empty-inline">${escapeHtml(__('No milestones are selected yet. Add one below if this project needs them.', 'coordina-project-wizard'))}</p>`;
	}
	return milestones.map((milestone, index) => `<div class="coordina-project-wizard-builder-row ${milestone.selected === false ? 'is-muted' : ''}"><div class="coordina-project-wizard-builder-row__top"><label class="coordina-checkbox"><input type="checkbox" ${milestone.selected === false ? '' : 'checked'} data-project-wizard-array="milestones" data-project-wizard-index="${index}" data-project-wizard-field="selected" /><span>${escapeHtml(__('Include milestone', 'coordina-project-wizard'))}</span></label><button type="button" class="button button-small" data-action="project-wizard-remove-item" data-array="milestones" data-index="${index}">${escapeHtml(__('Remove', 'coordina-project-wizard'))}</button></div><div class="coordina-project-wizard-builder-fields"><label><span>${escapeHtml(__('Milestone', 'coordina-project-wizard'))}</span><input type="text" value="${escapeHtml(milestone.title || '')}" data-project-wizard-array="milestones" data-project-wizard-index="${index}" data-project-wizard-field="title" /></label><label><span>${escapeHtml(__('Target date', 'coordina-project-wizard'))}</span><input type="datetime-local" value="${escapeHtml(dateTimeInputValue(milestone.due_date || ''))}" data-project-wizard-array="milestones" data-project-wizard-index="${index}" data-project-wizard-field="due_date" /></label><label><span>${escapeHtml(__('Status', 'coordina-project-wizard'))}</span><select data-project-wizard-array="milestones" data-project-wizard-index="${index}" data-project-wizard-field="status">${optionList(dropdowns.milestone_statuses || [], milestone.status || 'planned')}</select></label></div></div>`).join('');
}

function stepMilestones() {
	return `<section class="coordina-card coordina-card--wide coordina-form coordina-project-wizard-form"><div class="coordina-section-header"><div><h3>${escapeHtml(__('Choose milestones', 'coordina-project-wizard'))}</h3><p class="coordina-section-note">${escapeHtml(__('Template milestones start selected. Keep the ones you want, add dates, or uncheck anything that does not belong in this project.', 'coordina-project-wizard'))}</p></div><button type="button" class="button" data-action="project-wizard-add-item" data-array="milestones">${escapeHtml(__('Add milestone', 'coordina-project-wizard'))}</button></div><div class="coordina-project-wizard-builder-list">${milestoneRows()}</div></section>`;
}

function taskRows() {
	const wizard = wizardState();
	const tasks = Array.isArray(wizard.form && wizard.form.tasks) ? wizard.form.tasks : [];
	if (!tasks.length) {
		return `<p class="coordina-empty-inline">${escapeHtml(__('No tasks are selected yet. Add starter tasks below if needed.', 'coordina-project-wizard'))}</p>`;
	}
	return tasks.map((task, index) => `<div class="coordina-project-wizard-builder-row ${task.selected === false ? 'is-muted' : ''}"><div class="coordina-project-wizard-builder-row__top"><label class="coordina-checkbox"><input type="checkbox" ${task.selected === false ? '' : 'checked'} data-project-wizard-array="tasks" data-project-wizard-index="${index}" data-project-wizard-field="selected" /><span>${escapeHtml(__('Include task', 'coordina-project-wizard'))}</span></label><button type="button" class="button button-small" data-action="project-wizard-remove-item" data-array="tasks" data-index="${index}">${escapeHtml(__('Remove', 'coordina-project-wizard'))}</button></div><div class="coordina-project-wizard-builder-fields coordina-project-wizard-builder-fields--task"><label><span>${escapeHtml(__('Task', 'coordina-project-wizard'))}</span><input type="text" value="${escapeHtml(task.title || '')}" data-project-wizard-array="tasks" data-project-wizard-index="${index}" data-project-wizard-field="title" /></label><label><span>${escapeHtml(__('Phase', 'coordina-project-wizard'))}</span><input type="text" value="${escapeHtml(task.phase || '')}" data-project-wizard-array="tasks" data-project-wizard-index="${index}" data-project-wizard-field="phase" /></label><label><span>${escapeHtml(__('Due date', 'coordina-project-wizard'))}</span><input type="datetime-local" value="${escapeHtml(dateTimeInputValue(task.due_date || ''))}" data-project-wizard-array="tasks" data-project-wizard-index="${index}" data-project-wizard-field="due_date" /></label></div></div>`).join('');
}

function riskRows() {
	const wizard = wizardState();
	const dropdowns = wizard.bootstrap && wizard.bootstrap.dropdowns ? wizard.bootstrap.dropdowns : {};
	const risks = Array.isArray(wizard.form && wizard.form.risks) ? wizard.form.risks : [];
	if (!risks.length) {
		return `<p class="coordina-empty-inline">${escapeHtml(__('No risks are selected yet. Add them here if you want them created on day one.', 'coordina-project-wizard'))}</p>`;
	}
	return risks.map((risk, index) => `<div class="coordina-project-wizard-builder-row ${risk.selected === false ? 'is-muted' : ''}"><div class="coordina-project-wizard-builder-row__top"><label class="coordina-checkbox"><input type="checkbox" ${risk.selected === false ? '' : 'checked'} data-project-wizard-array="risks" data-project-wizard-index="${index}" data-project-wizard-field="selected" /><span>${escapeHtml(__('Include item', 'coordina-project-wizard'))}</span></label><button type="button" class="button button-small" data-action="project-wizard-remove-item" data-array="risks" data-index="${index}">${escapeHtml(__('Remove', 'coordina-project-wizard'))}</button></div><div class="coordina-project-wizard-builder-fields coordina-project-wizard-builder-fields--risk"><label><span>${escapeHtml(__('Risk or issue', 'coordina-project-wizard'))}</span><input type="text" value="${escapeHtml(risk.title || '')}" data-project-wizard-array="risks" data-project-wizard-index="${index}" data-project-wizard-field="title" /></label><label><span>${escapeHtml(__('Type', 'coordina-project-wizard'))}</span><select data-project-wizard-array="risks" data-project-wizard-index="${index}" data-project-wizard-field="object_type"><option value="risk" ${String(risk.object_type || 'risk') === 'risk' ? 'selected' : ''}>${escapeHtml(__('Risk', 'coordina-project-wizard'))}</option><option value="issue" ${String(risk.object_type || 'risk') === 'issue' ? 'selected' : ''}>${escapeHtml(__('Issue', 'coordina-project-wizard'))}</option></select></label><label><span>${escapeHtml(__('Severity', 'coordina-project-wizard'))}</span><select data-project-wizard-array="risks" data-project-wizard-index="${index}" data-project-wizard-field="severity">${optionList(dropdowns.severities || [], risk.severity || 'medium')}</select></label><label><span>${escapeHtml(__('Target resolution date', 'coordina-project-wizard'))}</span><input type="datetime-local" value="${escapeHtml(dateTimeInputValue(risk.target_resolution_date || ''))}" data-project-wizard-array="risks" data-project-wizard-index="${index}" data-project-wizard-field="target_resolution_date" /></label></div></div>`).join('');
}

function stepWork() {
	return `<section class="coordina-project-wizard-work-grid coordina-form coordina-project-wizard-form"><section class="coordina-card"><div class="coordina-section-header"><div><h3>${escapeHtml(__('Review starter tasks', 'coordina-project-wizard'))}</h3><p class="coordina-section-note">${escapeHtml(__('Template tasks start selected. Keep the ones you need, add dates, or add more work before the project is created.', 'coordina-project-wizard'))}</p></div><button type="button" class="button" data-action="project-wizard-add-item" data-array="tasks">${escapeHtml(__('Add task', 'coordina-project-wizard'))}</button></div><div class="coordina-project-wizard-builder-list">${taskRows()}</div></section><section class="coordina-card"><div class="coordina-section-header"><div><h3>${escapeHtml(__('Review starter risks', 'coordina-project-wizard'))}</h3><p class="coordina-section-note">${escapeHtml(__('Keep any template risks you want captured on day one, switch them to issues if needed, or add new ones.', 'coordina-project-wizard'))}</p></div><button type="button" class="button" data-action="project-wizard-add-item" data-array="risks">${escapeHtml(__('Add risk', 'coordina-project-wizard'))}</button></div><div class="coordina-project-wizard-builder-list">${riskRows()}</div></section></section>`;
}

function stepReview() {
	const wizard = wizardState();
	const form = wizard.form || {};
	const template = selectedTemplate();
	const milestoneItems = selectedItems(form.milestones);
	const taskItems = selectedItems(form.tasks);
	const riskItems = selectedItems(form.risks);
	const summaryLines = [
		renderSummaryLine(__('Template', 'coordina-project-wizard'), (template && template.title) || __('Not set', 'coordina-project-wizard')),
		renderSummaryLine(__('Title', 'coordina-project-wizard'), form.title || __('Not set', 'coordina-project-wizard')),
		renderSummaryLine(__('Code', 'coordina-project-wizard'), form.code || __('Not set', 'coordina-project-wizard')),
		renderSummaryLine(__('Status', 'coordina-project-wizard'), nice(form.status || 'planned')),
		renderSummaryLine(__('Priority and health', 'coordina-project-wizard'), `${nice(form.priority || 'normal')} • ${nice(form.health || 'neutral')}`),
		renderSummaryLine(__('Project manager', 'coordina-project-wizard'), findUserLabel(form.manager_user_id) || __('Current user', 'coordina-project-wizard')),
		renderSummaryLine(__('Sponsor', 'coordina-project-wizard'), findUserLabel(form.sponsor_user_id) || __('None', 'coordina-project-wizard')),
		renderSummaryLine(__('Timeline', 'coordina-project-wizard'), `${form.start_date ? dateLabel(form.start_date) : __('No start date', 'coordina-project-wizard')} -> ${form.target_end_date ? dateLabel(form.target_end_date) : __('No target end date', 'coordina-project-wizard')}`),
		renderSummaryLine(__('Team members', 'coordina-project-wizard'), String((form.team_member_ids || []).length)),
		renderSummaryLine(__('Visibility', 'coordina-project-wizard'), nice(form.visibility || 'team')),
		renderSummaryLine(__('Notifications', 'coordina-project-wizard'), nice(form.notification_policy || 'default')),
		renderSummaryLine(__('Task groups', 'coordina-project-wizard'), nice(form.task_group_label || 'stage')),
	];
	return `<section class="coordina-card coordina-card--wide"><div class="coordina-section-header"><div><h3>${escapeHtml(__('Final review', 'coordina-project-wizard'))}</h3><p class="coordina-section-note">${escapeHtml(__('Review the selected template, project fields, and starter records before the project is created.', 'coordina-project-wizard'))}</p></div></div><section class="coordina-project-wizard-review-summary"><div class="coordina-project-wizard-review-summary__grid">${summaryLines.join('')}</div><div class="coordina-summary-row coordina-summary-row--subtle"><span class="coordina-summary-chip"><strong>${milestoneItems.length}</strong>${escapeHtml(__('Milestones', 'coordina-project-wizard'))}</span><span class="coordina-summary-chip"><strong>${taskItems.length + (form.create_kickoff_task ? 1 : 0)}</strong>${escapeHtml(__('Tasks', 'coordina-project-wizard'))}</span><span class="coordina-summary-chip"><strong>${riskItems.length}</strong>${escapeHtml(__('Risks', 'coordina-project-wizard'))}</span></div><p class="coordina-section-note">${escapeHtml(form.create_kickoff_task ? __('A kickoff task will also be created automatically.', 'coordina-project-wizard') : __('No extra kickoff task will be added.', 'coordina-project-wizard'))}</p></section><div class="coordina-project-wizard-review-groups">${renderReviewSection(__('Selected milestones', 'coordina-project-wizard'), __('These milestone records will be created with the project.', 'coordina-project-wizard'), milestoneItems.map((item) => `<li><strong>${escapeHtml(item.title || '')}</strong>${item.due_date ? `<span>${escapeHtml(dateLabel(item.due_date))}</span>` : `<span>${escapeHtml(__('No date set', 'coordina-project-wizard'))}</span>`}</li>`))}${renderReviewSection(__('Selected tasks', 'coordina-project-wizard'), __('These starter tasks will be added to the project workspace.', 'coordina-project-wizard'), taskItems.map((item) => `<li><strong>${escapeHtml(item.title || '')}</strong><span>${escapeHtml(item.phase || __('No phase', 'coordina-project-wizard'))}${item.due_date ? ` • ${escapeHtml(dateLabel(item.due_date))}` : ''}</span></li>`))}${renderReviewSection(__('Selected risks and issues', 'coordina-project-wizard'), __('These items will be created immediately for tracking.', 'coordina-project-wizard'), riskItems.map((item) => `<li><strong>${escapeHtml(item.title || '')}</strong><span>${escapeHtml(`${nice(item.object_type || 'risk')} • ${nice(item.severity || 'medium')}`)}</span></li>`))}</div><div class="coordina-form-actions"><button type="button" class="button button-primary" data-action="project-wizard-create-project" ${wizard.isCreating ? 'disabled' : ''}>${escapeHtml(wizard.isCreating ? __('Creating...', 'coordina-project-wizard') : __('Create project workspace', 'coordina-project-wizard'))}</button></div></section>`;
}

function creationSummary() {
	const created = wizardState().created;
	if (!created || !created.project) {
		return '';
	}
	return `<section class="coordina-card coordina-card--notice coordina-project-wizard-success"><div class="coordina-section-header"><div><h3>${escapeHtml(__('Project created', 'coordina-project-wizard'))}</h3><p class="coordina-section-note">${escapeHtml(__('The project, starter work, milestones, and risks were created from your reviewed setup.', 'coordina-project-wizard'))}</p></div></div><div class="coordina-summary-row coordina-summary-row--subtle"><span class="coordina-summary-chip"><strong>${Number(created.groupsCreated || 0)}</strong>${escapeHtml(__('Groups', 'coordina-project-wizard'))}</span><span class="coordina-summary-chip"><strong>${Number(created.milestonesCreated || 0)}</strong>${escapeHtml(__('Milestones', 'coordina-project-wizard'))}</span><span class="coordina-summary-chip"><strong>${Number(created.tasksCreated || 0)}</strong>${escapeHtml(__('Tasks', 'coordina-project-wizard'))}</span><span class="coordina-summary-chip"><strong>${Number(created.risksCreated || 0)}</strong>${escapeHtml(__('Risks', 'coordina-project-wizard'))}</span></div><div class="coordina-inline-actions"><button type="button" class="button button-primary" data-action="project-wizard-open-project">${escapeHtml(__('Open workspace', 'coordina-project-wizard'))}</button><button type="button" class="button" data-action="project-wizard-start-over">${escapeHtml(__('Start another project', 'coordina-project-wizard'))}</button></div></section>`;
}

function stepBody() {
	if (currentStep() === 1) {
		return stepTemplate();
	}
	if (currentStep() === 2) {
		return stepCore();
	}
	if (currentStep() === 3) {
		return stepMilestones();
	}
	if (currentStep() === 4) {
		return stepWork();
	}
	return stepReview();
}

function navButtons() {
	const step = currentStep();
	return `<div class="coordina-form-actions coordina-project-wizard-nav-actions">${step > 1 ? `<button type="button" class="button" data-action="project-wizard-prev">${escapeHtml(__('Back', 'coordina-project-wizard'))}</button>` : '<span></span>'}${step < steps.length ? `<button type="button" class="button button-primary" data-action="project-wizard-next">${escapeHtml(__('Continue', 'coordina-project-wizard'))}</button>` : ''}</div>`;
}

function stepsHeader() {
	const step = currentStep();
	return `<div class="coordina-summary-row coordina-project-wizard-steps">${steps.map((item, index) => `<span class="coordina-summary-chip ${index + 1 === step ? 'is-active' : ''}"><strong>${index + 1}</strong>${escapeHtml(item.label)}</span>`).join('')}</div>`;
}

function pageBody() {
	const wizard = wizardState();
	if (wizard.loading || !wizard.loaded) {
		return `<section class="coordina-card" data-project-wizard-body="1"><div class="coordina-loading">${escapeHtml(__('Loading Project Wizard...', 'coordina-project-wizard'))}</div></section>`;
	}
	const stepKey = steps[currentStep() - 1] ? steps[currentStep() - 1].key : 'template';
	return `<section class="coordina-page coordina-project-wizard-page coordina-project-wizard-page--${escapeHtml(stepKey)}" data-project-wizard-body="1">${stepsHeader()}${creationSummary()}<div class="coordina-project-wizard-layout"><div class="coordina-project-wizard-main">${stepBody()}${!wizard.created ? navButtons() : ''}</div><aside class="coordina-project-wizard-side">${selectionSummaryCard()}${currentStep() === 1 ? defaultsCard() : ''}</aside></div></section>`;
}

function injectWizardBody() {
	if (app.state.page !== PAGE_KEY) {
		return;
	}
	const shell = root.querySelector('.coordina-shell');
	const header = shell ? shell.querySelector('.coordina-shell__header') : null;
	if (!shell || !header) {
		return;
	}
	const existing = shell.querySelector('[data-project-wizard-body="1"]');
	const wrapper = document.createElement('div');
	wrapper.innerHTML = pageBody();
	const nextBody = wrapper.firstElementChild;
	if (!nextBody) {
		return;
	}
	if (existing) {
		existing.replaceWith(nextBody);
		return;
	}
	const currentBody = header.nextElementSibling;
	if (currentBody) {
		currentBody.replaceWith(nextBody);
	} else {
		shell.appendChild(nextBody);
	}
}

async function loadWizard(force) {
	const wizard = wizardState();
	if (wizard.loaded && !force) {
		return;
	}
	wizard.loading = true;
	app.render();
	try {
		const data = await api('/project-wizard/bootstrap');
		wizard.bootstrap = data;
		wizard.form = buildInitialForm(data);
		wizard.settingsDraft = buildSettingsDraft(data);
		wizard.created = null;
		wizard.loaded = true;
		wizard.step = 1;
		bindListeners();
	} catch (error) {
		notify('error', error.message || __('Project Wizard could not load.', 'coordina-project-wizard'));
	} finally {
		wizard.loading = false;
		app.render();
	}
}

async function createProject() {
	const wizard = wizardState();
	const error = stepIsValid(2);
	if (error) {
		notify('error', error);
		wizard.step = 2;
		app.render();
		return;
	}
	wizard.isCreating = true;
	app.render();
	try {
		const result = await api('/project-wizard/projects', { method: 'POST', body: wizard.form });
		wizard.created = result;
		notify('success', __('Project created successfully.', 'coordina-project-wizard'));
	} catch (error) {
		notify('error', error.message || __('Project could not be created.', 'coordina-project-wizard'));
	} finally {
		wizard.isCreating = false;
		app.render();
	}
}

app.render = function () {
	originalRender();
	injectWizardBody();
};

app.handleAdminClickAction = async function (button) {
	const action = button.dataset.action || '';
	if (action === 'project-wizard-refresh') {
		await loadWizard(true);
		return;
	}
	if (action === 'project-wizard-open-project') {
		const created = wizardState().created;
		if (created && created.workspaceRoute) {
			openRoute(created.workspaceRoute);
		}
		return;
	}
	if (action === 'project-wizard-start-over') {
		resetWizard();
		return;
	}
	if (action === 'project-wizard-select-template') {
		applyTemplate(button.dataset.templateKey || '');
		app.render();
		return;
	}
	if (action === 'project-wizard-next') {
		moveStep(1);
		return;
	}
	if (action === 'project-wizard-prev') {
		moveStep(-1);
		return;
	}
	if (action === 'project-wizard-add-item') {
		const arrayName = button.dataset.array || '';
		if (arrayName && Array.isArray(wizardState().form && wizardState().form[arrayName])) {
			wizardState().form[arrayName].push(defaultArrayItem(arrayName));
			app.render();
		}
		return;
	}
	if (action === 'project-wizard-remove-item') {
		const arrayName = button.dataset.array || '';
		const index = Number(button.dataset.index || -1);
		if (arrayName && Array.isArray(wizardState().form && wizardState().form[arrayName]) && index >= 0) {
			wizardState().form[arrayName].splice(index, 1);
			app.render();
		}
		return;
	}
	if (action === 'project-wizard-create-project') {
		await createProject();
		return;
	}
	await originalClick(button);
	if (app.state.page === PAGE_KEY) {
		injectWizardBody();
	}
};

app.handleAdminSubmitEvent = async function (form) {
	const wizard = wizardState();
	if (form.dataset.action === 'project-wizard-settings') {
		const values = Object.fromEntries(new window.FormData(form).entries());
		form.querySelectorAll('input[type="checkbox"]').forEach((input) => {
			values[input.name] = input.checked;
		});
		wizard.isSavingSettings = true;
		app.render();
		try {
			const settings = await api('/project-wizard/settings', { method: 'POST', body: values });
			wizard.settingsDraft = buildSettingsDraft({ settings, templates: templates() });
			if (wizard.bootstrap) {
				wizard.bootstrap.settings = settings;
			}
			notify('success', __('Wizard defaults updated.', 'coordina-project-wizard'));
		} catch (error) {
			notify('error', error.message || __('Wizard defaults could not be updated.', 'coordina-project-wizard'));
		} finally {
			wizard.isSavingSettings = false;
			app.render();
		}
		return;
	}
	await originalSubmit(form);
	if (app.state.page === PAGE_KEY) {
		injectWizardBody();
	}
};

loadWizard(false);
app.render();
}());