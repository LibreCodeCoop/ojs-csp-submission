<?php

/**
 * @file plugins/generic/CspSubmission/CspSubmissionPlugin.inc.php
 *
 * Copyright (c) 2014-2019 LyseonTech
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CspSubmissionPlugin
 * @ingroup plugins_generic_CspSubmission
 *
 * @brief CspSubmission plugin class
 */

use APP\Services\QueryBuilders\SubmissionQueryBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpClient\HttpClient;

import('lib.pkp.classes.plugins.GenericPlugin');
require_once(dirname(__FILE__) . '/vendor/autoload.php');

class CspSubmissionPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success) {

			// Insert new field into author metadata submission form (submission step 3) and metadata form
			HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'metadataFieldEdit'));
			HookRegistry::register('TemplateManager::fetch', array($this, 'TemplateManager_fetch'));
			HookRegistry::register('TemplateManager::display',array(&$this, 'templateManager_display'));
			HookRegistry::register('FileManager::downloadFile',array($this, 'fileManager_downloadFile'));
			HookRegistry::register('Mail::send', array($this,'mail_send'));
			HookRegistry::register('submissionfilesuploadform::display', array($this,'submissionfilesuploadform_display'));

			HookRegistry::register('Submission::getMany::queryObject', array($this,'submission_getMany_queryObject'));

			HookRegistry::register('APIHandler::endpoints', array($this,'APIHandler_endpoints'));

			// Hook for initData in two forms -- init the new field
			HookRegistry::register('submissionsubmitstep3form::initdata', array($this, 'metadataInitData'));

			// Hook for readUserVars in two forms -- consider the new field entry
			HookRegistry::register('submissionsubmitstep3form::readuservars', array($this, 'metadataReadUserVars'));

			// Hook for execute in two forms -- consider the new field in the article settings
			HookRegistry::register('submissionsubmitstep3form::execute', array($this, 'metadataExecuteStep3'));
			HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'metadataExecuteStep4'));

			// Hook for save in two forms -- add validation for the new field
			HookRegistry::register('submissionsubmitstep3form::Constructor', array($this, 'addCheck'));

			// Consider the new field for ArticleDAO for storage
			HookRegistry::register('articledao::getAdditionalFieldNames', array($this, 'metadataReadUserVars'));

			HookRegistry::register('submissionfilesuploadform::validate', array($this, 'submissionfilesuploadformValidate'));

			HookRegistry::register('SubmissionHandler::saveSubmit', array($this, 'SubmissionHandler_saveSubmit'));
			HookRegistry::register('User::getMany::queryObject', array($this, 'pkp_services_pkpuserservice_getmany'));
			HookRegistry::register('UserDAO::_returnUserFromRowWithData', array($this, 'userDAO__returnUserFromRowWithData'));
			HookRegistry::register('User::getProperties::values', array($this, 'user_getProperties_values'));
			HookRegistry::register('authorform::initdata', array($this, 'authorform_initdata'));

			// This hook is used to register the components this plugin implements to
			// permit administration of custom block plugins.
			HookRegistry::register('LoadComponentHandler', array($this, 'setupGridHandler'));

			HookRegistry::register('userstageassignmentdao::_filterusersnotassignedtostageinusergroup', array($this, 'userstageassignmentdao_filterusersnotassignedtostageinusergroup'));

			HookRegistry::register('Template::Workflow::Publication', array($this, 'workflowFieldEdit'));

			HookRegistry::register('addparticipantform::execute', array($this, 'addparticipantformExecute'));

			HookRegistry::register('Publication::edit', array($this, 'publicationEdit'));
		}
		return $success;
	}

	/**
	 * Hooked to the the `display` callback in TemplateManager
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	public function templateManager_display($hookName, $args) {
		if ($args[1] == "submission/form/index.tpl") {

			$request =& Registry::get('request');
			$templateManager =& $args[0];

			// // Load JavaScript file
			$templateManager->addJavaScript(
				'coautor',
				$request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . '/js/build.js',
				array(
					'contexts' => 'backend',
					'priority' => STYLE_SEQUENCE_LAST,
				)
			);
			$templateManager->addStyleSheet(
				'coautor',
				$request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . '/styles/build.css',
				array(
					'contexts' => 'backend',
					'priority' => STYLE_SEQUENCE_LAST,
				)
			);
		} elseif ($args[1] == "dashboard/index.tpl") {
			$templateManager =& $args[0];
			$containerData = $templateManager->get_template_vars('containerData');
			$stages[] = $containerData['components']['myQueue']['filters'][1]['filters'][0];
			$stages[] = [
				'param' => 'substage',
				'value' => 1,
				'title' => 'Aguardando secretaria'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 2,
				'title' => 'Aguardando decisão'
			];
			$stages[] = $containerData['components']['myQueue']['filters'][1]['filters'][1];
			$stages[] = [
				'param' => 'substage',
				'value' => 3,
				'title' => 'Com o editor associado'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 4,
				'title' => 'Aguardando decisão'
			];
			$stages[] = $containerData['components']['myQueue']['filters'][1]['filters'][2];
			$stages[] = [
				'param' => 'substage',
				'value' => 5,
				'title' => 'Envio para avaliação de ilustração'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 6,
				'title' => 'Em avaliação de ilustração'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 7,
				'title' => 'Envio de Carta de aprovação'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 8,
				'title' => 'Em revisão/Tradução'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 9,
				'title' => 'Revisão/Tradução prontas'
			];
/* 			$stages[] = [
				'param' => 'substage',
				'value' => 10,
				'title' => 'Tradução de metadados'
			]; */
			$stages[] = $containerData['components']['myQueue']['filters'][1]['filters'][3];
			$stages[] = [
				'param' => 'substage',
				'value' => 11,
				'title' => 'Aguardando padronizador'
			];
/* 			$stages[] = [
				'param' => 'substage',
				'value' => 12,
				'title' => 'Padronização Concluída'
			]; */
			$stages[] = [
				'param' => 'substage',
				'value' => 13,
				'title' => 'Formatação de Figura'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 14,
				'title' => 'Produção de PDF padronizado'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 15,
				'title' => 'Prova de Prelo enviada'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 16,
				'title' => 'Prova de prelo recebida'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 17,
				'title' => 'Aguardando diagramação'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 18,
				'title' => 'PDF diagramado'
			];
			$stages[] = [
				'param' => 'substage',
				'value' => 19,
				'title' => 'Aguardando publicação'
			];
			$containerData['components']['myQueue']['filters'][1]['filters'] = $stages;
			$templateManager->assign('containerData', $containerData);
		}

		return false;
	}

	public function submission_getMany_queryObject($hookName, $args) {
		$request = \Application::get()->getRequest();
		$locale = AppLocale::getLocale();
		/**
		 * @var SubmissionQueryBuilder
		 */
		$qb = $args[0];
		$request = \Application::get()->getRequest();
		$substage = $request->getUserVar('substage');
		if ($substage) {
			$substage = $substage[0];
		}

		$uploadRevisorTradutor = Capsule::table('submission_files');
		$uploadRevisorTradutor->select(Capsule::raw('submission_files.submission_id'));
		$uploadRevisorTradutor->leftJoin('user_user_groups','user_user_groups.user_id','=','submission_files.uploader_user_id');
		$uploadRevisorTradutor->where('user_user_groups.user_group_id', '=', 7); // Arquivo de upload realizado por revisor / tradutor

		$queryDiagramadorDesignado = Capsule::table('stage_assignments');
		$queryDiagramadorDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
		$queryDiagramadorDesignado->where('stage_assignments.user_group_id', '=', 22);  // Designadas a diagramadores

		$queryEditorFiguraDesignado = Capsule::table('stage_assignments');
		$queryEditorFiguraDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
		$queryEditorFiguraDesignado->where('stage_assignments.user_group_id', '=', 21);  // Designadas a editores de figura

		$queryUploadFiguraAlterada = Capsule::table('submission_files');
		$queryUploadFiguraAlterada->select(Capsule::raw('submission_files.submission_id'));
		$queryUploadFiguraAlterada->leftJoin('submission_file_settings','submission_file_settings.file_id','=','submission_files.file_id');
		$queryUploadFiguraAlterada->where('submission_files.file_stage', '=', 6); // Box Arquivos para edição de texto
		$queryUploadFiguraAlterada->where('submission_file_settings.setting_value', 'LIKE', '%Figura_alterada%');
		$queryUploadFiguraAlterada->where('submission_file_settings.locale', '=', $locale);

		$queryDiscussaoProvaPrelo = Capsule::table('queries');
		$queryDiscussaoProvaPrelo->select(Capsule::raw('queries.assoc_id'));
		$queryDiscussaoProvaPrelo->leftJoin('query_participants','query_participants.query_id','=','queries.query_id');
		$queryDiscussaoProvaPrelo->leftJoin('notes','notes.assoc_id','=','queries.query_id');
		$queryDiscussaoProvaPrelo->where('notes.title', 'LIKE', '%prelo%'); // Com discussão de prova de prelo

/* 				$queryUploadAutor = Capsule::table('submission_files');
		$queryUploadAutor->select(Capsule::raw('submission_files.submission_id'));
		$queryUploadAutor->leftJoin('user_user_groups','user_user_groups.user_id','=','submission_files.uploader_user_id');
		$queryUploadAutor->where('user_user_groups.user_group_id', '=', 14); // Autor
		$queryUploadAutor->where('submission_files.file_stage', '=', 18); // Box Discussão da Editoração */

		$queryDiscussaoProvaPreloRespondida = Capsule::table('queries');
		$queryDiscussaoProvaPreloRespondida->select(Capsule::raw('queries.assoc_id'));
		$queryDiscussaoProvaPreloRespondida->leftJoin('query_participants','query_participants.query_id','=','queries.query_id');
		$queryDiscussaoProvaPreloRespondida->leftJoin('notes','notes.assoc_id','=','queries.query_id');
		$queryDiscussaoProvaPreloRespondida->leftJoin('user_user_groups','user_user_groups.user_id','=','notes.user_id');
		$queryDiscussaoProvaPreloRespondida->where('user_user_groups.user_group_id', '=', 14); // Autor
		$queryDiscussaoProvaPreloRespondida->where('queries.stage_id', '=', 5); // Estágio editoração
		$queryDiscussaoProvaPreloRespondida->whereNull('notes.title'); // Com discussão de prova de prelo respondida

		$queryRevisorTradutorDesignado = Capsule::table('stage_assignments');
		$queryRevisorTradutorDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
		$queryRevisorTradutorDesignado->where('stage_assignments.user_group_id', '=', 7);  // Designadas a revisor/tradutor

		$queryUploadFiguraParaFormatar = Capsule::table('submission_files');
		$queryUploadFiguraParaFormatar->select(Capsule::raw('submission_files.submission_id'));
		$queryUploadFiguraParaFormatar->leftJoin('submission_file_settings','submission_file_settings.file_id','=','submission_files.file_id');
		$queryUploadFiguraParaFormatar->where('submission_files.file_stage', '=', 11); // Box Arquivos prontos para o Leiaute
		$queryUploadFiguraParaFormatar->where('submission_file_settings.setting_value', 'LIKE', '%Figura_para_formatar%');
		$queryUploadFiguraParaFormatar->where('submission_file_settings.locale', '=', $locale); // Arquivo Figura para formatar no box Arquivos prontos para o Leiaute

		$queryUploadFiguraFormatada = Capsule::table('submission_files');
		$queryUploadFiguraFormatada->select(Capsule::raw('submission_files.submission_id'));
		$queryUploadFiguraFormatada->leftJoin('submission_file_settings','submission_file_settings.file_id','=','submission_files.file_id');
		$queryUploadFiguraFormatada->where('submission_files.file_stage', '=', 11); // Box Arquivos prontos para o Leiaute
		$queryUploadFiguraFormatada->where('submission_file_settings.setting_value', 'LIKE', '%Figura_formatada%');
		$queryUploadFiguraFormatada->where('submission_file_settings.locale', '=', $locale); // Arquivo Figura formatada no box Arquivos prontos para o Leiaute

		switch ($substage) {
			case 1: // Aguardando secretaria
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$queryEditorChefeDesignado = Capsule::table('stage_assignments');
				$queryEditorChefeDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
				$queryEditorChefeDesignado->where('stage_assignments.user_group_id', '=', 3);

				$qb->whereNotIn('s.submission_id',$queryEditorChefeDesignado); // Não designadas a editores chefe

			break;
			case 2: // Aguardando decisão
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$qb->where(function ($qb) {
					$queryEditorChefeDesignado = Capsule::table('stage_assignments');
					$queryEditorChefeDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
					$queryEditorChefeDesignado->where('stage_assignments.user_group_id', '=', 3);

					$qb->where('s.stage_id', '=', 1); // No estágio submissão
					$qb->whereIn('s.submission_id',$queryEditorChefeDesignado); // Designadas a editores chefe
				});

				$qb->orWhere(function ($qb) {
					$queryEditorAssociadoDesignado = Capsule::table('stage_assignments');
					$queryEditorAssociadoDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
					$queryEditorAssociadoDesignado->where('stage_assignments.user_group_id', '=', 5);

					$qb->where('s.stage_id', '=', 3); // No estágio avaliação
					$qb->WhereNotIn('s.submission_id', $queryEditorAssociadoDesignado); // Não designada a editor associado
				});


			break;
			case 3: // Com o editor associado
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$qb->leftJoin('edit_decisions as ed','ed.submission_id','=','s.submission_id');

				$lastdecisionIds = Capsule::table('edit_decisions');
				$lastdecisionIds->select(Capsule::raw('MAX(edit_decisions.edit_decision_id)'));
				$lastdecisionIds->leftJoin('user_user_groups','user_user_groups.user_id','=','edit_decisions.editor_id');
				$lastdecisionIds->where('edit_decisions.decision','<>',16); // Onde a última decisão não foi nova rodada de avaliaçao
				$lastdecisionIds->groupBy('edit_decisions.submission_id');

				$editoresAssociados = Capsule::table('user_user_groups');
				$editoresAssociados->select(Capsule::raw('user_user_groups.user_id'));
				$editoresAssociados->where('user_user_groups.user_group_id', '=', 3);

				//Última decisão feita pelos editores chefes
				$qb->whereIn('ed.edit_decision_id',$lastdecisionIds); // Pega útima decisão excluindo nova rodada de avaliação
				$qb->whereIn('ed.editor_id', $editoresAssociados); // Onde a última decisão foi dada por um editor chefe
				$qb->where('s.stage_id', '=', 3);

			break;
			case 4: // Aguardando decisão
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$qb->leftJoin('edit_decisions as ed','ed.submission_id','=','s.submission_id');

				$qb->where(function ($qb) {
					$lastdecisionIds = Capsule::table('edit_decisions');
					$lastdecisionIds->select(Capsule::raw('MAX(edit_decisions.edit_decision_id)'));
					$lastdecisionIds->leftJoin('user_user_groups','user_user_groups.user_id','=','edit_decisions.editor_id');
					$lastdecisionIds->where('edit_decisions.decision','<>',16); // Onde a última decisão não foi nova rodada de avaliaçao
					$lastdecisionIds->groupBy('edit_decisions.submission_id');

					$editoresAssociados = Capsule::table('user_user_groups');
					$editoresAssociados->select(Capsule::raw('user_user_groups.user_id'));
					$editoresAssociados->where('user_user_groups.user_group_id', '=', 5);

					$qb->where('s.stage_id', '=', 3);
					$qb->whereIn('ed.edit_decision_id',$lastdecisionIds);
					$qb->whereIn('ed.editor_id', $editoresAssociados);
				});

				$qb->orWhere(function ($qb) {

					$queryEditorAssociadoDesignado = Capsule::table('stage_assignments');
					$queryEditorAssociadoDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
					$queryEditorAssociadoDesignado->where('stage_assignments.user_group_id', '=', 5);

					$qb->where('s.stage_id', '=', 3);
					$qb->WhereNotIn('s.submission_id', $queryEditorAssociadoDesignado);

				});

			break;
			case 5: // Aguardando envio para avaliação de ilustração
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$queryRevisorFiguraNãoDesignado = Capsule::table('stage_assignments');
				$queryRevisorFiguraNãoDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
				$queryRevisorFiguraNãoDesignado->where('stage_assignments.user_group_id', '=', 19); // Designadas a revisor de figura

				$queryRevisorTradutorDesignado = Capsule::table('stage_assignments');
				$queryRevisorTradutorDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
				$queryRevisorTradutorDesignado->where('stage_assignments.user_group_id', '=', 7);  // Designadas a revisor/tradutor

				$qb->whereNotIn('s.submission_id',$queryRevisorFiguraNãoDesignado); // Não designadas a revisor de figura
				$qb->whereNotIn('s.submission_id',$queryRevisorTradutorDesignado); // Não designadas a revisor / tradutor
				$qb->where('s.stage_id', '=', 4);

			break;
			case 6: // Em avaliação de ilustração'
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$queryRevisorFiguraDesignado = Capsule::table('stage_assignments');
				$queryRevisorFiguraDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
				$queryRevisorFiguraDesignado->where('stage_assignments.user_group_id', '=', 19); // Designadas a revisor de figura

/* 				$queryDiscussaoRevisaoFigura = Capsule::table('queries');
				$queryDiscussaoRevisaoFigura->select(Capsule::raw('queries.assoc_id'));
				$queryDiscussaoRevisaoFigura->leftJoin('query_participants','query_participants.query_id','=','queries.query_id');
				$queryDiscussaoRevisaoFigura->leftJoin('notes','notes.assoc_id','=','queries.query_id');
				$queryDiscussaoRevisaoFigura->where('notes.title', 'LIKE', '%figura%');
				$queryDiscussaoRevisaoFigura->where('queries.closed', '=', 0); // Com discussão de revisão de figura em aberto
				$qb->whereIn('s.submission_id',$queryDiscussaoRevisaoFigura);*/

				$qb->whereIn('s.submission_id',$queryRevisorFiguraDesignado); // Designadas a revisor de figura
				$qb->whereNotIn('s.submission_id',$queryUploadFiguraAlterada); // Sem upload de figura alterada
				$qb->where('s.stage_id', '=', 4);

			break;
			case 7: // Aguardando envio de Carta de aprovação
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$queryCartaAprovacao = Capsule::table('queries');
				$queryCartaAprovacao->select(Capsule::raw('queries.assoc_id'));
				$queryCartaAprovacao->leftJoin('query_participants','query_participants.query_id','=','queries.query_id');
				$queryCartaAprovacao->leftJoin('notes','notes.assoc_id','=','queries.query_id');
				$queryCartaAprovacao->where('notes.title', 'LIKE', '%aprova%'); // Discussão de aprovação

				$qb->whereIn('s.submission_id',$queryUploadFiguraAlterada); // Com figura revisada no box de Arquivos para edição de texto
				$qb->whereNotIn('s.submission_id',$queryCartaAprovacao); // Com carta de aprovação não enviada
				$qb->where('s.stage_id', '=', 4);

			break;
			case 8: //Aguardando revisão/Tradução'
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$qb->whereIn('s.submission_id',$queryRevisorTradutorDesignado); // Com Revisor/Tradutor designado
				$qb->whereNotIn('s.submission_id',$uploadRevisorTradutor); // Sem arquivo de upload realizado por revisor/tradutor
				$qb->where('s.stage_id', '=', 4);

			break;
			case 9: // Revisão/Tradução prontas
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$qb->whereIn('s.submission_id',$queryRevisorTradutorDesignado); // Com Revisor/Tradutor designado
				$qb->whereIn('s.submission_id',$uploadRevisorTradutor); // Com arquivo de upload realizado por revisor/tradutor
				$qb->where('s.stage_id', '=', 4);

			break;
			case 11: // Aguardando padronizador
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$queryPadronizadorDesignado = Capsule::table('stage_assignments');
				$queryPadronizadorDesignado->select(Capsule::raw('DISTINCT stage_assignments.submission_id'));
				$queryPadronizadorDesignado->where('stage_assignments.user_group_id', '=', 20);  // Designadas a padrozinador

				$qb->whereNotIn('s.submission_id',$queryPadronizadorDesignado);
				$qb->where('s.stage_id', '=', 5);

			break;
			case 13: // Aguardando formatação de Figura
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

/* 				$queryDiscussaoEdicaoFigura = Capsule::table('queries');
				$queryDiscussaoEdicaoFigura->select(Capsule::raw('queries.assoc_id'));
				$queryDiscussaoEdicaoFigura->leftJoin('query_participants','query_participants.query_id','=','queries.query_id');
				$queryDiscussaoEdicaoFigura->leftJoin('notes','notes.assoc_id','=','queries.query_id');
				$queryDiscussaoEdicaoFigura->where('notes.title', 'LIKE', '%figura%');
				$queryDiscussaoEdicaoFigura->where('queries.closed', '=', 0); // Com discussão de edição de figura em aberto */

				$qb->whereIn('s.submission_id',$queryEditorFiguraDesignado); // Designada a editor de figura
				$qb->whereIn('s.submission_id',$queryUploadFiguraParaFormatar); // Com arquivo Figura para formatar no box Arquivos prontos para Layout
				$qb->whereNotIn('s.submission_id',$queryUploadFiguraFormatada); // Sem arquivo Figura formatada no box Arquivos prontos para Layout
				$qb->where('s.stage_id', '=', 5);

			break;
			case 14: // Aguardando produção de PDF padronizado
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$qb->whereIn('s.submission_id',$queryUploadFiguraFormatada); // Com arquivo Figura formatada no box Arquivos prontos para Layout
				$qb->whereNotIn('s.submission_id',$queryDiscussaoProvaPrelo); // Sem discussão de prova de prelo aberta
				$qb->where('s.stage_id', '=', 5);

			break;
			case 15: // Prova de Prelo e declaração enviadas
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$qb->whereIn('s.submission_id',$queryDiscussaoProvaPrelo); // Com discussão de prova de prelo
				$qb->whereNotIn('s.submission_id',$queryDiscussaoProvaPreloRespondida); // Sem discussão de prova de prelo respondida pelo autor
				//$qb->whereNotIn('s.submission_id',$queryUploadAutor); // Sem arquivo enviado por autor no box Discussão da Editoração
				$qb->where('s.stage_id', '=', 5);

			break;
			case 16: // Prova de Prelo e declaração recebidas
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$queryDiscussaoProvaPreloRespondida = Capsule::table('queries');
				$queryDiscussaoProvaPreloRespondida->select(Capsule::raw('queries.assoc_id'));
				$queryDiscussaoProvaPreloRespondida->leftJoin('query_participants','query_participants.query_id','=','queries.query_id');
				$queryDiscussaoProvaPreloRespondida->leftJoin('notes','notes.assoc_id','=','queries.query_id');
				$queryDiscussaoProvaPreloRespondida->leftJoin('user_user_groups','user_user_groups.user_id','=','notes.user_id');
				$queryDiscussaoProvaPreloRespondida->where('user_user_groups.user_group_id', '=', 14); // Autor
				$queryDiscussaoProvaPreloRespondida->where('queries.stage_id', '=', 5); // Estágio editoração
				$queryDiscussaoProvaPreloRespondida->whereNull('notes.title'); // Com discussão de prova de prelo respondida

				$qb->whereIn('s.submission_id',$queryDiscussaoProvaPreloRespondida); // Com discussão de prova de prelo respondida pelo autor
				$qb->where('s.stage_id', '=', 5);

			break;
			case 17: // Aguardando diagramação
				unset($qb->wheres[2]);
				unset($qb->joins[0]->wheres[1]);
				unset($qb->joins[1]);

				$qb->whereIn('s.submission_id',$queryDiagramadorDesignado);
				$qb->where('s.stage_id', '=', 5);

			break;
		}
		$params = $args[1];
	}

	/**
	 * Permit requests to the custom block grid handler
	 * @param $hookName string The name of the hook being invoked
	 * @param $args array The parameters to the invoked hook
	 */
	function setupGridHandler($hookName, $params) {
		$component =& $params[0];
		if ($component == 'plugins.generic.CspSubmission.controllers.grid.AddAuthorHandler') {
			return true;
		}
		return false;
	}

	function addparticipantformExecute($hookName, $args){
		$args[0]->_data["userGroupId"] = 1;
		$request = \Application::get()->getRequest();
	}

	function mail_send($hookName, $args){
		//return;
		$request = \Application::get()->getRequest();
		$stageId = $request->getUserVar('stageId');
		$decision = $request->getUserVar('decision');
		$submissionId = $request->getUserVar('submissionId');

		if($stageId == 3 && $decision == 1){  // AO ACEITAR SUBMISSÃO, OS EDITORES ASSISTENTES DEVEM SER NOTIFICADOS

			$request = \Application::get()->getRequest();
			$submissionId = $request->getUserVar('submissionId');
			$stageId = $request->getUserVar('stageId');
			$locale = AppLocale::getLocale();

			import('lib.pkp.classes.mail.MailTemplate');

			$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
			$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 24);
			unset($args[0]->_data["recipients"]);
			while ($user = $users->next()) {
				$args[0]->_data["recipients"][]= ["name" => $user->getFullName(), "email" => $user->getEmail()];
			}

		}



		if($request->_router->_page == "reviewer"){ // AVALIADOR RECEBE E-MAIL DE AGRADECIMENTO APÓS SUBMETER AVALIAÇÃO
			if($request->_requestVars["step"] == 1){
				return true;
			}
			if($request->_requestVars["step"] == 3){

				$locale = AppLocale::getLocale();
				$userDao = DAORegistry::getDAO('UserDAO');
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT a.email_key, a.body, a.subject

					FROM

					(
						SELECT 	d.email_key, d.body, d.subject
						FROM 	email_templates_default_data d
						WHERE 	d.locale = '$locale'

						UNION ALL

						SELECT 	t.email_key, o.body, o.subject
						FROM 	ojs.email_templates t

						LEFT JOIN
						(
							SELECT 	a.body, b.subject, a.email_id
							FROM
							(
								SELECT 	setting_value as body, email_id
								FROM 	email_templates_settings
								WHERE 	setting_name = 'body' AND locale = '$locale'
							)a
							LEFT JOIN
							(
									SELECT 	setting_value as subject, email_id
									FROM 	email_templates_settings
									WHERE 	setting_name = 'subject' AND locale = '$locale'
							)b
							ON a.email_id = b.email_id
						) o
						ON o.email_id = t.email_id
						WHERE t.enabled = 1
					) a
					WHERE 	a.email_key  = 'REVIEW_THANK'

					QUERY
				);
				/// O EMAIL ESTÁ SENDO EVIADO DIVERSAR VEZES PARA O AVALIADO - RESOLVER !!!!
				$args[0]->_data['body'] = $result->GetRowAssoc(false)['body'];
				$args[0]->_data['subject'] = $result->GetRowAssoc(false)['subject'];
				$args[0]->_data["from"]["name"] = "CSP";
				$args[0]->_data["from"]["email"] = "noreply@lt.coop.br";
				$args[0]->_data["recipients"][0]["name"] = $args[0]->params["senderName"];
				$args[0]->_data["recipients"][0]["email"] = $args[0]->params["senderEmail"];
			}

		}
		if($args[0]->emailKey == "REVISED_VERSION_NOTIFY"){ // QUANDO AUTOR SUBMETE TEXTO REVISADO, EMAIL VAI PARA SECRETARIA

			unset($args[0]->_data["recipients"]);

			$locale = AppLocale::getLocale();

			$userDao = DAORegistry::getDAO('UserDAO');
			$result = $userDao->retrieve(
				<<<QUERY
				SELECT u.email, x.setting_value as name
				FROM ojs.stage_assignments a
				LEFT JOIN ojs.users u
				ON a.user_id = u.user_id
				LEFT JOIN (SELECT user_id, setting_value FROM ojs.user_settings WHERE setting_name = 'givenName' AND locale = '$locale') x
				ON x.user_id = u.user_id
				WHERE submission_id = $submissionId AND user_group_id = 23
				QUERY
			);

			while (!$result->EOF) {
				$args[0]->_data["recipients"][] =  array("name" => $result->GetRowAssoc(0)['name'], "email" => $result->GetRowAssoc(0)['email']);
				//$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];

				$result->MoveNext();
			}
		}
		$args[0]->_data["from"]["name"] = "Cadernos de Saúde Pública";
		$args[0]->_data["from"]["email"] = "noreply@fiocruz.br";
		$args[0]->_data["replyTo"][0]["name"] =  "Cadernos de Saúde Pública";
		$args[0]->_data["replyTo"][0]["email"] = "noreply@fiocruz.br";
	}

	public function APIHandler_endpoints($hookName, $args) {
		if (isset($args[0]['GET'])) {
			foreach($args[0]['GET'] as $key => $endpoint) {
				if ($endpoint['pattern'] == '/{contextPath}/api/{version}/users') {
					if (!in_array(ROLE_ID_AUTHOR, $endpoint['roles'])) {
						$args[0]['GET'][$key]['roles'][] = ROLE_ID_AUTHOR;
					}
				}
			}
		}
	}

	function TemplateManager_fetch($hookName, $args) {
		$args[1];
		$templateMgr =& $args[0];
		$request = \Application::get()->getRequest();
		$stageId = $request->getUserVar('stageId');
		$submissionId = $request->getUserVar('submissionId');
		//$itemId = $request->getUserVar('istemId');

		if ($args[1] == 'controllers/grid/users/reviewer/form/createReviewerForm.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('createReviewerForm.tpl'));

			return true;
		} elseif ($args[1] == 'submission/form/step3.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('step3.tpl'));

			return true;

		} elseif ($args[1] == 'controllers/grid/gridCell.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('gridCell.tpl'));

			return true;
		} elseif ($args[1] == 'controllers/wizard/fileUpload/form/fileUploadConfirmationForm.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('fileUploadConfirmationForm.tpl'));

			return true;
		} elseif ($args[1] == 'controllers/wizard/fileUpload/form/submissionArtworkFileMetadataForm.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('submissionArtworkFileMetadataForm.tpl'));

			return true;
		} elseif ($args[1] == 'controllers/grid/users/author/form/authorForm.tpl') {
			$request = \Application::get()->getRequest();
			$operation = $request->getRouter()->getRequestedOp($request);
			switch ($operation) {
				case 'addAuthor':
					if ($request->getUserVar('userId')) {
						$args[4] = $templateMgr->fetch($this->getTemplateResource('authorFormAdd.tpl'));
						return true;
					}
					import('plugins.generic.cspSubmission.controllers.list.autor.CoautorListPanel');

					$coautorListHandler = new CoautorListPanel(
						'CoautorListPanel',
						__('plugins.generic.CspSubmission.searchForAuthor'),
						[
							'apiUrl' => $request->getDispatcher()->url($request, ROUTE_API, $request->getContext()->getPath(), 'users'),
							'getParams' => [
								'roleIds' => [ROLE_ID_AUTHOR],
								'orderBy' => 'givenName',
								'orderDirection' => 'ASC'
							]
						]
					);

					$templateMgr->assign('containerData', ['components' => ['CoautorListPanel' => $coautorListHandler->getConfig()]]);
					$templateMgr->assign('basejs', $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . '/js/build.js');
					$templateMgr->assign('basecss', $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . '/styles/build.css');

					$args[4] = $templateMgr->fetch($this->getTemplateResource('authorForm.tpl'));
					return true;
				case 'updateAuthor':
					$templateMgr->assign('csrfToken', $request->getSession()->getCSRFToken());
					$args[4] = $templateMgr->fetch($this->getTemplateResource('authorFormAdd.tpl'));
					return true;
			}
		} elseif ($args[1] == 'controllers/modals/submissionMetadata/form/issueEntrySubmissionReviewForm.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('issueEntrySubmissionReviewForm.tpl'));

			return true;
		} elseif ($args[1] == 'controllers/grid/users/reviewer/form/advancedSearchReviewerForm.tpl') {

			$request = \Application::get()->getRequest();
			$submissionDAO = Application::getSubmissionDAO();
			$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
			$templateMgr->assign('title',$submission->getTitle(AppLocale::getLocale()));
			$args[4] = $templateMgr->fetch($this->getTemplateResource('advancedSearchReviewerForm.tpl'));

			return true;
		}elseif ($args[1] == 'reviewer/review/step1.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('reviewStep1.tpl'));

			return true;
		}elseif ($args[1] == 'reviewer/review/step3.tpl') {
			$templateMgr->assign(array(
				'reviewerRecommendationOptions' =>	array(
															'' => 'common.chooseOne',
															SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT => 'reviewer.article.decision.accept',
															SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS => 'reviewer.article.decision.pendingRevisions',
															SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE => 'reviewer.article.decision.decline',
														)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('reviewStep3.tpl'));
			return true;
		}elseif ($args[1] == 'reviewer/review/reviewCompleted.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('reviewCompleted.tpl'));

			return true;
		}elseif ($args[1] == 'controllers/grid/users/stageParticipant/addParticipantForm.tpl') {
			if($stageId == 5){
				$locale = AppLocale::getLocale();
				$userDao = DAORegistry::getDAO('UserDAO');
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT t.email_key, o.body, o.subject
					FROM email_templates t
					LEFT JOIN
					(
						SELECT a.body, b.subject, a.email_id
						FROM
						(
							SELECT setting_value as body, email_id
							FROM ojs.email_templates_settings
							WHERE setting_name = 'body' AND locale = '$locale'
						)a
						LEFT JOIN
						(
								SELECT setting_value as subject, email_id
								FROM ojs.email_templates_settings
								WHERE setting_name = 'subject' AND locale = '$locale'
						)b
						ON a.email_id = b.email_id
					) o
					ON o.email_id = t.email_id
					WHERE t.enabled = 1 AND t.email_key LIKE 'LAYOUT%'
					QUERY
				);
				$i = 0;
				while (!$result->EOF) {
					$i++;
					$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
					$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];

					$result->MoveNext();
				}

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign(array(
					'templates' => $templateSubject,
					//'stageId' => $stageId,
					//'submissionId' => $this->_submissionId,
					//'itemId' => $this->_itemId,
					'message' => json_encode($templateBody),
					'comment' => reset($templateBody)
				));

				$args[4] = $templateMgr->fetch($this->getTemplateResource('addParticipantForm.tpl'));

				return true;
			}elseif($stageId == 4){
				$locale = AppLocale::getLocale();
				$userDao = DAORegistry::getDAO('UserDAO');
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT t.email_key, o.body, o.subject
					FROM email_templates t
					LEFT JOIN
					(
						SELECT a.body, b.subject, a.email_id
						FROM
						(
							SELECT setting_value as body, email_id
							FROM ojs.email_templates_settings
							WHERE setting_name = 'body' AND locale = '$locale'
						)a
						LEFT JOIN
						(
								SELECT setting_value as subject, email_id
								FROM ojs.email_templates_settings
								WHERE setting_name = 'subject' AND locale = '$locale'
						)b
						ON a.email_id = b.email_id
					) o
					ON o.email_id = t.email_id
					WHERE t.enabled = 1 AND t.email_key LIKE 'COPYEDIT%'
					QUERY
				);
				$i = 0;
				while (!$result->EOF) {
					$i++;
					$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
					$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];

					$result->MoveNext();
				}

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign(array(
					'templates' => $templateSubject,
					//'stageId' => $stageId,
					//'submissionId' => $this->_submissionId,
					//'itemId' => $this->_itemId,
					'message' => json_encode($templateBody),
					'comment' => reset($templateBody)
				));

				$args[4] = $templateMgr->fetch($this->getTemplateResource('addParticipantForm.tpl'));

				return true;

			}elseif($stageId == 3 OR $stageId == 1){
				$locale = AppLocale::getLocale();
				$userDao = DAORegistry::getDAO('UserDAO');
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT t.email_key, o.body, o.subject
					FROM email_templates t
					LEFT JOIN
					(
						SELECT a.body, b.subject, a.email_id
						FROM
						(
							SELECT setting_value as body, email_id
							FROM ojs.email_templates_settings
							WHERE setting_name = 'body' AND locale = '$locale'
						)a
						LEFT JOIN
						(
								SELECT setting_value as subject, email_id
								FROM ojs.email_templates_settings
								WHERE setting_name = 'subject' AND locale = '$locale'
						)b
						ON a.email_id = b.email_id
					) o
					ON o.email_id = t.email_id
					WHERE t.enabled = 1 AND t.email_key = 'EDITOR_ASSIGN'
					QUERY
				);
				$i = 0;
				while (!$result->EOF) {
					$i++;
					$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
					$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];

					$result->MoveNext();
				}

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign(array(
					'templates' => $templateSubject,
					//'stageId' => $stageId,
					//'submissionId' => $this->_submissionId,
					//'itemId' => $this->_itemId,
					'message' => json_encode($templateBody),
					'comment' => reset($templateBody)
				));

				$args[4] = $templateMgr->fetch($this->getTemplateResource('addParticipantForm.tpl'));

				return true;

			}

		}elseif ($args[1] == 'controllers/modals/editorDecision/form/promoteForm.tpl') {
			$decision = $request->_requestVars["decision"];
			if ($stageId == 3 or $stageId == 1){
				if($decision == 1){

						$request = \Application::get()->getRequest();
						$submissionId = $request->getUserVar('submissionId');

						$userDao = DAORegistry::getDAO('UserDAO');
						$result = $userDao->retrieve(
							<<<QUERY
							SELECT s.user_group_id , g.user_id, a.user_id as assigned
							FROM ojs.user_user_groups g
							LEFT JOIN ojs.user_group_settings s
							ON s.user_group_id = g.user_group_id
							LEFT JOIN ojs.stage_assignments a
							ON g.user_id = a.user_id AND a.submission_id = $submissionId
							WHERE s.setting_value = 'Assistente editorial'
							QUERY
						);
						while (!$result->EOF) {

							if($result->GetRowAssoc(0)['assigned'] == NULL){

								$userGroupId = $result->GetRowAssoc(0)['user_group_id'];
								$userId = $result->GetRowAssoc(0)['user_id'];

								$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
								$stageAssignment = $stageAssignmentDao->newDataObject();
								$stageAssignment->setSubmissionId($submissionId);
								$stageAssignment->setUserGroupId($userGroupId);
								$stageAssignment->setUserId($userId);
								$stageAssignment->setRecommendOnly(1);
								$stageAssignment->setCanChangeMetadata(1);
								$stageAssignmentDao->insertObject($stageAssignment);

								$submissionDAO = Application::getSubmissionDAO();
								$submission = $submissionDAO->getById($submissionId);

								$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
								$assignedUser = $userDao->getById($userId);
								$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
								$userGroup = $userGroupDao->getById($userGroupId);

								import('lib.pkp.classes.log.SubmissionLog');
								SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_ADD_PARTICIPANT, 'submission.event.participantAdded', array('name' => $assignedUser->getFullName(), 'username' => $assignedUser->getUsername(), 'userGroupName' => $userGroup->getLocalizedName()));

							}

							$result->MoveNext();
						}

				}
				//$templateMgr->assign('skipEmail',1); // PASSA VARIÁVEL PARA NÃO ENVIAR EMAIL PARA O AUTOR

				$args[4] = $templateMgr->fetch($this->getTemplateResource('promoteFormStage1And3.tpl'));

				return true;

			}elseif ($stageId == 4){
				$templateMgr->assign('skipEmail',1); // PASSA VARIÁVEL PARA NÃO ENVIAR EMAIL PARA O AUTOR
				$args[4] = $templateMgr->fetch($this->getTemplateResource('promoteFormStage4.tpl'));

				return true;
			}
		}elseif ($args[1] == 'controllers/modals/editorDecision/form/sendReviewsForm.tpl') {

			$decision = $request->_requestVars["decision"];

			if ($decision == 2){ // BOTÃO SOLICITAR MODIFICAÇÕES
				/*

				$templateMgr->assign('skipEmail',0); // PASSA VARIÁVEL PARA ENVIAR EMAIL PARA O AUTOR
				$templateMgr->assign('decision',3); // PASSA VARIÁVEL PARA SELECIONAR O CAMPO " Solicitar modificações ao autor que estarão sujeitos a avaliação futura."

				$locale = AppLocale::getLocale();
				$userDao = DAORegistry::getDAO('UserDAO');
				$result = $userDao->retrieve(
					<<<QUERY

					SELECT a.email_key, a.body, a.subject

					FROM

					(
						SELECT 	d.email_key, d.body, d.subject
						FROM 	email_templates_default_data d
						WHERE 	d.locale = '$locale'

						UNION ALL

						SELECT 	t.email_key, o.body, o.subject
						FROM 	ojs.email_templates t

						LEFT JOIN
						(
							SELECT 	a.body, b.subject, a.email_id
							FROM
							(
								SELECT 	setting_value as body, email_id
								FROM 	email_templates_settings
								WHERE 	setting_name = 'body' AND locale = '$locale'
							)a
							LEFT JOIN
							(
									SELECT 	setting_value as subject, email_id
									FROM 	email_templates_settings
									WHERE 	setting_name = 'subject' AND locale = '$locale'
							)b
							ON a.email_id = b.email_id
						) o
						ON o.email_id = t.email_id
						WHERE t.enabled = 1
					) a
					WHERE 	a.email_key LIKE 'REQUEST_REVISIONS%'

					QUERY
				);
				$i = 0;
				while (!$result->EOF) {
					$i++;
					$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
					$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];

					$result->MoveNext();
				}

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign(array(
					'templates' => $templateSubject,
					'stageId' => $stageId,
					'message' => json_encode($templateBody),
					'default' => reset($templateBody)
				));						 */

			}elseif ($decision == 4 or $decision == 9){  // BOTÃO REJEITAR SUBMISSÃO
				return;
				$locale = AppLocale::getLocale();
				$userDao = DAORegistry::getDAO('UserDAO');
				$result = $userDao->retrieve(
					<<<QUERY

					SELECT a.email_key, a.body, a.subject

					FROM

					(
						SELECT 	d.email_key, d.body, d.subject
						FROM 	email_templates_default_data d
						WHERE 	d.locale = '$locale'

						UNION ALL

						SELECT 	t.email_key, o.body, o.subject
						FROM 	ojs.email_templates t

						LEFT JOIN
						(
							SELECT 	a.body, b.subject, a.email_id
							FROM
							(
								SELECT 	setting_value as body, email_id
								FROM 	email_templates_settings
								WHERE 	setting_name = 'body' AND locale = '$locale'
							)a
							LEFT JOIN
							(
									SELECT 	setting_value as subject, email_id
									FROM 	email_templates_settings
									WHERE 	setting_name = 'subject' AND locale = '$locale'
							)b
							ON a.email_id = b.email_id
						) o
						ON o.email_id = t.email_id
						WHERE t.enabled = 1
					) a
					WHERE 	a.email_key LIKE 'EDITOR_DECISION_INITIAL_DECLINE%'

					QUERY
				);
				$i = 0;
				while (!$result->EOF) {
					$i++;
					$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
					$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];

					$result->MoveNext();
				}

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign(array(
					'templates' => $templateSubject,
					'stageId' => $stageId,
					'message' => json_encode($templateBody),
					'default' => reset($templateBody)
				));
			}

			$args[4] = $templateMgr->fetch($this->getTemplateResource('sendReviewsForm.tpl'));

			return true;

		}elseif ($args[1] == 'controllers/grid/queries/form/queryForm.tpl' && $stageId == "1") {
			$locale = AppLocale::getLocale();
			$userDao = DAORegistry::getDAO('UserDAO');
			$result = $userDao->retrieve(
				<<<QUERY
				SELECT t.email_key, o.body, o.subject
				FROM email_templates t
				LEFT JOIN
				(
					SELECT a.body, b.subject, a.email_id
					FROM
					(
						SELECT setting_value as body, email_id
						FROM ojs.email_templates_settings
						WHERE setting_name = 'body' AND locale = '$locale'
					)a
					LEFT JOIN
					(
							SELECT setting_value as subject, email_id
							FROM ojs.email_templates_settings
							WHERE setting_name = 'subject' AND locale = '$locale'
					)b
					ON a.email_id = b.email_id
				) o
				ON o.email_id = t.email_id
				WHERE t.enabled = 1 AND t.email_key LIKE 'PRE_AVALIACAO%'
				QUERY
			);
			$i = 0;
			while (!$result->EOF) {
				$i++;
				$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
				$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];

				$result->MoveNext();
			}

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'templates' => $templateSubject,
				'stageId' => $stageId,
				'submissionId' => $this->_submissionId,
				'itemId' => $this->_itemId,
				'message' => json_encode($templateBody),
				'comment' => reset($templateBody)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('queryForm.tpl'));

			return true;

		}elseif ($args[1] == 'controllers/grid/queries/form/queryForm.tpl' && $stageId == "4") {
			$locale = AppLocale::getLocale();
			$userDao = DAORegistry::getDAO('UserDAO');
			$userId = $_SESSION["userId"];
			$result = $userDao->retrieve( // VERIFICA SE O PERFIL É AUTOR
				<<<QUERY
				SELECT g.user_group_id , g.user_id
				FROM ojs.user_user_groups g
				WHERE g.user_group_id = 14 AND user_id = $userId
				QUERY
			);

			if($result->_numOfRows == 0){

				$result = $userDao->retrieve(
					<<<QUERY
					SELECT t.email_key, o.body, o.subject
					FROM email_templates t
					LEFT JOIN
					(
						SELECT a.body, b.subject, a.email_id
						FROM
						(
							SELECT setting_value as body, email_id
							FROM ojs.email_templates_settings
							WHERE setting_name = 'body' AND locale = '$locale'
						)a
						LEFT JOIN
						(
								SELECT setting_value as subject, email_id
								FROM ojs.email_templates_settings
								WHERE setting_name = 'subject' AND locale = '$locale'
						)b
						ON a.email_id = b.email_id
					) o
					ON o.email_id = t.email_id
					WHERE t.enabled = 1 AND t.email_key LIKE 'EDICAO_TEXTO%'
					QUERY
				);
			}else{
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT t.email_key, o.body, o.subject
					FROM email_templates t
					LEFT JOIN
					(
						SELECT a.body, b.subject, a.email_id
						FROM
						(
							SELECT setting_value as body, email_id
							FROM ojs.email_templates_settings
							WHERE setting_name = 'body' AND locale = '$locale'
						)a
						LEFT JOIN
						(
								SELECT setting_value as subject, email_id
								FROM ojs.email_templates_settings
								WHERE setting_name = 'subject' AND locale = '$locale'
						)b
						ON a.email_id = b.email_id
					) o
					ON o.email_id = t.email_id
					WHERE t.enabled = 1 AND t.email_key LIKE 'EDICAO_TEXTO_APROVD%'
					QUERY
				);
			}
			$i = 0;
			while (!$result->EOF) {
				$i++;
				$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
				$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];

				$result->MoveNext();
			}

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'templates' => $templateSubject,
				'stageId' => $stageId,
				'submissionId' => $this->_submissionId,
				'itemId' => $this->_itemId,
				'message' => json_encode($templateBody),
				'comment' => reset($templateBody)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('queryForm.tpl'));

			return true;
		}elseif ($args[1] == 'controllers/grid/queries/form/queryForm.tpl' && $stageId == "5") {
			$locale = AppLocale::getLocale();
			$userDao = DAORegistry::getDAO('UserDAO');
			$result = $userDao->retrieve(
				<<<QUERY
				SELECT t.email_key, o.body, o.subject
				FROM email_templates t
				LEFT JOIN
				(
					SELECT a.body, b.subject, a.email_id
					FROM
					(
						SELECT setting_value as body, email_id
						FROM ojs.email_templates_settings
						WHERE setting_name = 'body' AND locale = '$locale'
					)a
					LEFT JOIN
					(
							SELECT setting_value as subject, email_id
							FROM ojs.email_templates_settings
							WHERE setting_name = 'subject' AND locale = '$locale'
					)b
					ON a.email_id = b.email_id
				) o
				ON o.email_id = t.email_id
				WHERE t.enabled = 1 AND t.email_key LIKE 'EDITORACAO%'
				QUERY
			);
			$i = 0;
			while (!$result->EOF) {
				$i++;
				$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
				$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];

				$result->MoveNext();
			}
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'templates' => $templateSubject,
				'stageId' => $stageId,
				'submissionId' => $submissionId,
				//'itemId' => $itemId,
				'message' => json_encode($templateBody),
				'comment' => reset($templateBody)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('queryForm.tpl'));

			return true;

		}elseif ($args[1] == 'controllers/wizard/fileUpload/form/fileUploadForm.tpl') {

			$args[4] = $templateMgr->fetch($this->getTemplateResource('fileUploadForm.tpl'));

			return true;

		} elseif ($args[1] == 'controllers/wizard/fileUpload/form/submissionFileMetadataForm.tpl'){
			$tplvars = $templateMgr->getFBV();
			$locale = AppLocale::getLocale();

			$genreId = $tplvars->_form->_submissionFile->_data["genreId"];
			if($genreId == 47){ // SEM PRE-DEFINIÇÃO DE GÊNERO

				$tplvars->_form->_submissionFile->_data["name"][$locale] = "csp_".$request->_requestVars["submissionId"]."_".date("Y")."_".$tplvars->_form->_submissionFile->_data["originalFileName"];

			}else{

				$userDao = DAORegistry::getDAO('UserDAO');
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT setting_value
					FROM ojs.genre_settings
					WHERE genre_id = $genreId AND locale = '$locale'
					QUERY
				);
				$genreName = $result->GetRowAssoc(false)['setting_value'];
				$genreName = str_replace(" ","_",$genreName);

				$extensao = pathinfo($tplvars->_form->_submissionFile->_data["originalFileName"], PATHINFO_EXTENSION);

				$tplvars->_form->_submissionFile->_data["name"][$locale] = "csp_".$request->_requestVars["submissionId"]."_".date("Y")."_".$genreName.".".$extensao;

			}

		} elseif ($args[1] == 'controllers/grid/users/reviewer/readReview.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('readReview.tpl'));

			return true;

		} elseif ($args[1] == 'controllers/modals/editorDecision/form/recommendationForm.tpl'){
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'skipEmail' => true,
				'recommendationOptions' =>	array(
												'' => 'common.chooseOne',
												SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS => 'editor.submission.decision.requestRevisions',
												SUBMISSION_EDITOR_RECOMMEND_ACCEPT => 'editor.submission.decision.accept',
												SUBMISSION_EDITOR_RECOMMEND_DECLINE => 'editor.submission.decision.decline',
											)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('recommendationForm.tpl'));
			return true;

		} elseif ($args[1] == 'controllers/grid/gridRow.tpl' && $request->_requestPath == '/ojs/index.php/csp/$$$call$$$/grid/users/user-select/user-select-grid/fetch-grid'){
			$templateMgr = TemplateManager::getManager($request);
			$columns = $templateMgr->getVariable('columns');
			$cells = $templateMgr->getVariable('cells');
			$row = $templateMgr->getVariable('row');
			$cells->value[] = $row->value->_data->_data["assigns"];
			$columns->value['assigns'] = clone $columns->value["name"];
			$columns->value["assigns"]->_title = "author.users.contributor.assign";

		}




		return false;
	}

	public function submissionfilesuploadform_display($hookName, $args)
	{
		/** @var Request */
		$request = \Application::get()->getRequest();
		$fileStage = $request->getUserVar('fileStage');
		$submissionDAO = Application::getSubmissionDAO();
		$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
		$submissionProgress = $submission->getData('submissionProgress');
		$stageId = $request->getUserVar('stageId');
		$userId = $_SESSION["userId"];
		$locale = AppLocale::getLocale();
		$userDao = DAORegistry::getDAO('UserDAO');

		$templateMgr =& $args[0];

		if ($fileStage == 2 && $submissionProgress == 0){

/* 			$templateMgr->setData('revisionOnly',false);
			$templateMgr->setData('isReviewAttachment',true);
			$templateMgr->setData('submissionFileOptions',[]);
 */
			//$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES

			$result = $userDao->retrieve(
				<<<QUERY
				SELECT A.genre_id, setting_value
				FROM ojs.genre_settings A
				LEFT JOIN ojs.genres B
				ON B.genre_id = A.genre_id
				WHERE locale = '$locale' AND entry_key = 'SUBMISSAO_PDF'
				QUERY
			);
			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

				$result->MoveNext();
			}

			$templateMgr->setData('submissionFileGenres', $genreList);
			$templateMgr->setData('isReviewAttachment', false); // SETA A VARIÁVEL PARA FALSE POIS ELA É VERIFICADA NO TEMPLATE PARA EXIBIR OS COMPONENTES
		}

		if ($fileStage == 4) { // SECRETARIA FAZENDO UPLOAD DE NOVA VERSÃO

			$result = $userDao->retrieve(
				<<<QUERY
				SELECT A.genre_id, setting_value
				FROM ojs.genre_settings A
				LEFT JOIN ojs.genres B
				ON B.genre_id = A.genre_id
				WHERE locale = '$locale' AND entry_key LIKE 'AVAL_SECRETARIA%'
				QUERY
			);
			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

				$result->MoveNext();
			}

			$templateMgr->setData('submissionFileGenres', $genreList);
			$templateMgr->setData('isReviewAttachment', false); // SETA A VARIÁVEL PARA FALSE POIS ELA É VERIFICADA NO TEMPLATE PARA EXIBIR OS COMPONENTES

		}

		if ($fileStage == 5) { // AVALIADOR FAZENDO UPLOAD DE PARECER

			$result = $userDao->retrieve(
				<<<QUERY
				SELECT A.genre_id, setting_value
				FROM ojs.genre_settings A
				LEFT JOIN ojs.genres B
				ON B.genre_id = A.genre_id
				WHERE locale = '$locale' AND entry_key LIKE 'AVAL_AVALIADOR%'
				QUERY
			);
			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

				$result->MoveNext();
			}

			$templateMgr->setData('submissionFileGenres', $genreList);
			$templateMgr->setData('isReviewAttachment', false); // SETA A VARIÁVEL PARA FALSE POIS ELA É VERIFICADA NO TEMPLATE PARA EXIBIR OS COMPONENTES

		}
		if ($fileStage == 6) {

			$result = $userDao->retrieve( // PEGA O PERFIL
				<<<QUERY
				SELECT g.user_group_id
				FROM ojs.user_user_groups g
				WHERE user_id = $userId
				QUERY
			);

			while (!$result->EOF) {
				if($result->GetRowAssoc(0)['user_group_id'] == 19){ // PERFIL REVISOR DE FIGURA
					$result_genre = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'EDICAO_TEXTO_FIG_ALT%'
						QUERY
					);
				break;
				}

				$result->MoveNext();
			}

			if(isset($result_genre)){

				while (!$result_genre->EOF) {
					$genreList[$result_genre->GetRowAssoc(0)['genre_id']] = $result_genre->GetRowAssoc(0)['setting_value'];

					$result_genre->MoveNext();
				}

				$templateMgr->setData('submissionFileGenres', $genreList);

			}else{
				$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
			}			

		}
		if ($fileStage == 9) { // UPLOAD DE ARQUIVO EM BOX DE ARQUIVOS DE REVISÃO DE TEXTO

			$result = $userDao->retrieve( // VERIFICA SE O PERFIL É DE REVISOR/TRADUTOR
				<<<QUERY
				SELECT g.user_group_id , g.user_id
				FROM ojs.user_user_groups g
				WHERE g.user_group_id = 7 AND user_id = $userId
				QUERY
			);

			if($result->_numOfRows == 0){
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT A.genre_id, setting_value
					FROM ojs.genre_settings A
					LEFT JOIN ojs.genres B
					ON B.genre_id = A.genre_id
					WHERE locale = '$locale' AND entry_key LIKE 'EDICAO_ASSIST_ED%'
					QUERY
				);
			}else{
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT A.genre_id, setting_value
					FROM ojs.genre_settings A
					LEFT JOIN ojs.genres B
					ON B.genre_id = A.genre_id
					WHERE locale = '$locale' AND entry_key LIKE 'EDICAO_TRADUT%'
					QUERY
				);
			}

			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

				$result->MoveNext();
			}

			$templateMgr->setData('submissionFileGenres', $genreList);

		}
		if ($fileStage == 10) { // UPLOAD DE PDF PARA PUBLICAÇÃO
			$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
		}
		if ($fileStage == 11) { // UPLOAD DE ARQUIVO EM BOX DE ARQUIVOS PRONTOS PARA LAYOUT

			$result = $userDao->retrieve( // CONSULTA O PERFIL
				<<<QUERY
				SELECT g.user_group_id
				FROM ojs.user_user_groups g
				WHERE user_id = $userId
				QUERY
			);

			while (!$result->EOF) {
				if($result->GetRowAssoc(0)['user_group_id'] == 24){ // ASSISTENTE EDITORIAL
					$result_genre = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND (entry_key LIKE 'EDITORACAO_ASSIS_ED_TEMPLT%' OR entry_key LIKE 'EDITORACAO_FIG_P_FORMATAR%')
						QUERY
					);
				break;
				}elseif($result->GetRowAssoc(0)['user_group_id'] == 21){ // EDITOR DE FIGURA
					$result_genre = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'EDITORACAO_FIG_FORMATA%'
						QUERY
					);
				break;
				}

				$result->MoveNext();
			}

			if(isset($result_genre)){

				while (!$result_genre->EOF) {
					$genreList[$result_genre->GetRowAssoc(0)['genre_id']] = $result_genre->GetRowAssoc(0)['setting_value'];

					$result_genre->MoveNext();
				}

				$templateMgr->setData('submissionFileGenres', $genreList);

			}else{
				$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
			}
		}

		if ($fileStage == 15) { // UPLOAD NOVA VERSÃO



			$result = $userDao->retrieve( // BUSCAR PERFIL DO USUÁRIO
				<<<QUERY
				SELECT g.user_group_id
				FROM ojs.user_user_groups g
				WHERE user_id = $userId
				QUERY
			);

			while (!$result->EOF) {
				if($result->GetRowAssoc(0)['user_group_id'] == 23){ // SECRETARIA
					$result_genre = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'AVAL_SECRETARIA_NOVA_VERSAO%'
						QUERY
					);
				break;
				}elseif($result->GetRowAssoc(0)['user_group_id'] == 14){ // AUTOR
					$result_genre = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'AVAL_AUTOR%'
						QUERY
					);
				break;
				}

				$result->MoveNext();
			}

			if(isset($result_genre)){

				while (!$result_genre->EOF) {
					$genreList[$result_genre->GetRowAssoc(0)['genre_id']] = $result_genre->GetRowAssoc(0)['setting_value'];

					$result_genre->MoveNext();
				}

				$templateMgr->setData('submissionFileGenres', $genreList);
				$templateMgr->setData('alert', 'É obrigatória a submissão de uma carta ao editor associado escolhendo o componete "Alterações realizadas"');

			}else{
				$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
			}

		}

		if ($fileStage == 17) { // ARQUIVOS DEPENDENTES EM PUBLICAÇÃO
			$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES

		}
		if ($fileStage == 18) {  // UPLOADS NO BOX DISCUSSÃO

			if($stageId == 5){

				$autor = $userDao->retrieve( // VERIFICA SE O PERFIL É DE AUTOR PARA EXIBIR SOMENTE OS COMPONENTES DO PERFIL
					<<<QUERY
					SELECT g.user_group_id , g.user_id
					FROM ojs.user_user_groups g
					WHERE g.user_group_id = 14 AND user_id = $userId
					QUERY
				);

				$editor_assistente = $userDao->retrieve( // VERIFICA SE O PERFIL É DE ASSISTENTE EDITORIAL PARA EXIBIR SOMENTE OS COMPONENTES DO PERFIL
					<<<QUERY
					SELECT g.user_group_id , g.user_id
					FROM ojs.user_user_groups g
					WHERE g.user_group_id = 24 AND user_id = $userId
					QUERY
				);

				if($autor->_numOfRows > 0){
					$result = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'EDITORACAO_AUTOR%'
						QUERY
					);

					while (!$result->EOF) {
						$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

						$result->MoveNext();
					}

					$templateMgr->setData('submissionFileGenres', $genreList);
				}elseif($editor_assistente->_numOfRows > 0) {
					$result = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'EDITORACAO_ASSIST_ED%'
						QUERY
					);

					while (!$result->EOF) {
						$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

						$result->MoveNext();
					}

					$templateMgr->setData('submissionFileGenres', $genreList);
				}else{
					$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
				}


			}elseif($stageId == 4){

				$result = $userDao->retrieve( // VERIFICA SE O PERFIL É DE AUTOR PARA EXIBIR SOMENTE OS COMPONENTES DO PERFIL
					<<<QUERY
					SELECT g.user_group_id , g.user_id
					FROM ojs.user_user_groups g
					WHERE g.user_group_id = 14 AND user_id = $userId
					QUERY
				);

				if($result->_numOfRows > 0){
					$result = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'EDICAO_TEXTO_FIG_ALT%'
						QUERY
					);
					while (!$result->EOF) {
						$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

						$result->MoveNext();
					}

					$templateMgr->setData('submissionFileGenres', $genreList);
				}else{
					$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
				}

			}else{
				$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
			}

		}

	}

	function pkp_services_pkpuserservice_getmany($hookName, $args)
	{
		$refObject   = new ReflectionObject($args[1]);
		$refReviewStageId = $refObject->getProperty('reviewStageId');
		$refReviewStageId->setAccessible( true );
		$reviewStageId = $refReviewStageId->getValue($args[1]);

		if (!$reviewStageId && strpos($_SERVER["HTTP_REFERER"], 'submission/wizard')  ){
			$refObject   = new ReflectionObject($args[1]);
			$refColumns = $refObject->getProperty('columns');
			$refColumns->setAccessible( true );
			$columns = $refColumns->getValue($args[1]);
			$columns[] = Capsule::raw("trim(concat(ui1.setting_value, ' ', COALESCE(ui2.setting_value, ''))) AS instituicao");
			$columns[] = Capsule::raw('\'ojs\' AS type');
			$refColumns->setValue($args[1], $columns);

			$cspQuery = Capsule::table(Capsule::raw('csp.Pessoa p'));
			$cspQuery->leftJoin('users as u', function ($join) {
				$join->on('u.email', '=', 'p.email');
			});
			$cspQuery->whereNull('u.email');
			$cspQuery->whereIn('p.permissao', [0,2,3]);

			$refSearchPhrase = $refObject->getProperty('searchPhrase');
			$refSearchPhrase->setAccessible( true );
			$words = $refSearchPhrase->getValue($args[1]);
			if ($words) {
				$words = explode(' ', $words);
				if (count($words)) {
					foreach ($words as $word) {
						$cspQuery->where(function($q) use ($word) {
							$q->where(Capsule::raw('lower(p.nome)'), 'LIKE', "%{$word}%")
								->orWhere(Capsule::raw('lower(p.email)'), 'LIKE', "%{$word}%")
								->orWhere(Capsule::raw('lower(p.orcid)'), 'LIKE', "%{$word}%");
						});
					}
				}
			}

			$locale = AppLocale::getLocale();
			$args[0]->leftJoin('user_settings as ui1', function ($join) use ($locale) {
				$join->on('ui1.user_id', '=', 'u.user_id')
					->where('ui1.setting_name', '=', 'instituicao1')
					->where('ui1.locale', '=', $locale);
			});
			$args[0]->leftJoin('user_settings as ui2', function ($join) use ($locale) {
				$join->on('ui2.user_id', '=', 'u.user_id')
					->where('ui2.setting_name', '=', 'instituicao2')
					->where('ui2.locale', '=', $locale);
			});

			if (property_exists($args[1], 'countOnly')) {
				$refCountOnly = $refObject->getProperty('countOnly');
				$refCountOnly->setAccessible( true );
				if ($refCountOnly->getValue($args[1])) {
					$cspQuery->select(['p.idPessoa']);
					$args[0]->select(['u.user_id'])
						->groupBy('u.user_id');
				}
			} else {
				$userDao = DAORegistry::getDAO('UserDAO');
				// retrieve all columns of table users
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT `COLUMN_NAME`
					  FROM `INFORMATION_SCHEMA`.`COLUMNS`
					 WHERE `TABLE_SCHEMA`='ojs'
					   AND `TABLE_NAME`='users';
					QUERY
				);
				while (!$result->EOF) {
					$columnsNames[$result->GetRowAssoc(0)['column_name']] = 'null';
					$result->MoveNext();
				}
				// assign custom values to columns
				$columnsNames['user_id'] = "CONCAT('CSP|',p.idPessoa)";
				$columnsNames['email'] = 'p.email';
				$columnsNames['user_given'] = "SUBSTRING_INDEX(SUBSTRING_INDEX(p.nome, ' ', 1), ' ', -1)";
				$columnsNames['user_family'] = "TRIM( SUBSTR(p.nome, LOCATE(' ', p.nome)) )";
				$columnsNames['instituicao'] = 'p.instituicao1';
				$columnsNames['type'] = '\'csp\'';
				foreach ($columnsNames as $name => $value) {
					$cspQuery->addSelect(Capsule::raw($value . ' AS ' . $name));
				}
				$args[0]->select($columns)
					->groupBy('u.user_id', 'user_given', 'user_family');
			}

			$subOjsQuery = Capsule::table(Capsule::raw(
				<<<QUERY
				(
					{$args[0]->toSql()}
					UNION
					{$cspQuery->toSql()}
				) as u
				QUERY
			));
			$subOjsQuery->mergeBindings($args[0]);
			$subOjsQuery->mergeBindings($cspQuery);
			$refColumns->setValue($args[1], ['*']);
			$args[0] = $subOjsQuery;
		}
	}

	function userDAO__returnUserFromRowWithData($hookName, $args)
	{
		list($user, $row) = $args;
		if (isset($row['type'])) {
			if ($row['type'] == 'csp') {
				$locale = AppLocale::getLocale();
				$user->setData('id', (int)explode('|', $row['user_id'])[1]);
				$user->setData('familyName', [$locale => $row['user_family']]);
				$user->setData('givenName', [$locale => $row['user_given']]);
			}
			$user->setData('type', $row['type']);
			$user->setData('instituicao', $row['instituicao']);
		}elseif(isset($row['assigns'])){
			$user->setData('assigns', $row['assigns']);
		}
	}

	function userstageassignmentdao_filterusersnotassignedtostageinusergroup($hookName, $args){
		$args[0] = <<<QUERY
					SELECT q1.*, COALESCE(q2.assigns,0) AS assigns FROM ({$args[0]}) q1
					LEFT JOIN (SELECT COUNT(*) AS assigns, user_id
					FROM ojs.stage_assignments a
					JOIN ojs.submissions s
					ON s.submission_id = a.submission_id AND s.stage_id <= 3
					WHERE a.user_group_id = ?
					GROUP BY a.user_id) q2
					ON q1.user_id = q2.user_id
					QUERY;
		$args[1][] = $args[1][10];


	}

	function user_getProperties_values($hookName, $args)
	{
		list(&$values, $user) = $args;
		$type = $user->getData('type');
		if ($type) {
			$values['type'] = $type;
			$values['instituicao'] = $user->getData('instituicao');
		}
	}

	public function authorform_initdata($hookName, $args)
	{
		$request = \Application::get()->getRequest();
		$type = $request->getUserVar('type');
		if ($type != 'csp') {
			return;
		}

		$form = $args[0];
		$form->setTemplate($this->getTemplateResource('authorFormAdd.tpl'));

		$userDao = DAORegistry::getDAO('UserDAO');
		$userCsp = $userDao->retrieve(
			<<<QUERY
			SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(p.nome, ' ', 1), ' ', -1) as given_name,
					TRIM( SUBSTR(p.nome, LOCATE(' ', p.nome)) ) family_name,
					email, orcid,
					TRIM(CONCAT(p.instituicao1, ' ', p.instituicao2)) AS affiliation
				FROM csp.Pessoa p
				WHERE p.idPessoa = ?
			QUERY,
			[(int) $request->getUserVar('userId')]
		)->GetRowAssoc(0);
		$locale = AppLocale::getLocale();
		$form->setData('givenName', [$locale => $userCsp['given_name']]);
		$form->setData('familyName', [$locale => $userCsp['family_name']]);
		$form->setData('affiliation', [$locale => $userCsp['affiliation']]);
		$form->setData('email', $userCsp['email']);
		$form->setData('orcid', $userCsp['orcid']);

		$args[0] = $form;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.CspSubmission.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.CspSubmission.description');
	}


	function workflowFieldEdit($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];
		$output .= $smarty->fetch($this->getTemplateResource('ExclusaoPrefixo.tpl'));
		return false;
	}

	/**
	 * Insert Campo1 field into author submission step 3 and metadata edit form
	 */
	function metadataFieldEdit($hookName, $params) {

		$submissionDAO = Application::getSubmissionDAO();
		$request = \Application::get()->getRequest();
		/** @val Submission */
		$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
		$publication = $submission->getCurrentPublication();
		$sectionId = $publication->getData('sectionId');

		$smarty =& $params[1];
		$output =& $params[2];
		//$output .= $smarty->fetch($this->getTemplateResource('RemovePrefixoTitulo.tpl'));

		if($sectionId == 5){
			$output .= $smarty->fetch($this->getTemplateResource('Revisao.tpl'));
		}

		if($sectionId == 4){
			$output .= $smarty->fetch($this->getTemplateResource('Tema.tpl'));
			$output .= $smarty->fetch($this->getTemplateResource('codigoTematico.tpl'));
		}

		$output .= $smarty->fetch($this->getTemplateResource('conflitoInteresse.tpl'));
		//$output .= $smarty->fetch($this->getTemplateResource('FonteFinanciamento.tpl'));
		$output .= $smarty->fetch($this->getTemplateResource('agradecimentos.tpl'));

		if($sectionId == 6){
			$output .= $smarty->fetch($this->getTemplateResource('codigoArtigoRelacionado.tpl'));
		}

		$output .= $smarty->fetch($this->getTemplateResource('InclusaoAutores.tpl'));


		return false;
	}

 	function metadataReadUserVars($hookName, $params) {
		$userVars =& $params[1];
		$userVars[] = 'conflitoInteresse';
		//$userVars[] = 'conflitoInteresseQual';
		//$userVars[] = 'FonteFinanciamento';
		//$userVars[] = 'FonteFinanciamentoQual';
		$userVars[] = 'agradecimentos';
		$userVars[] = 'codigoTematico';
		$userVars[] = 'Tema';
		$userVars[] = 'codigoArtigoRelacionado';
		$userVars[] = 'CodigoArtigo';
		//$userVars[] = 'doi';

		return false;
	}

 	function metadataExecuteStep3($hookName, $params) {
		$form =& $params[0];
		$article = $form->submission;
		$article->setData('conflitoInteresse', $form->getData('conflitoInteresse'));
		//$article->setData('conflitoInteresseQual', $form->getData('conflitoInteresseQual'));
		//$article->setData('FonteFinanciamento', $form->getData('FonteFinanciamento'));
		//$article->setData('FonteFinanciamentoQual', $form->getData('FonteFinanciamentoQual'));
		$article->setData('agradecimentos', $form->getData('agradecimentos'));
		$article->setData('codigoTematico', $form->getData('codigoTematico'));
		$article->setData('Tema', $form->getData('Tema'));
		$article->setData('codigoArtigoRelacionado', $form->getData('codigoArtigoRelacionado'));
		//$article->setData('doi', $form->getData('doi'));

		return false;
	}

	function metadataExecuteStep4($hookName, $params) {
		$form =& $params[0];
		$article = $form->submission;
		$userDao = DAORegistry::getDAO('UserDAO');
		$result = $userDao->retrieve(
			<<<QUERY
			SELECT CONCAT(LPAD(count(*)+1, CASE WHEN count(*) > 9999 THEN 5 ELSE 4 END, 0), '/', DATE_FORMAT(now(), '%y')) code
			FROM submissions
			WHERE YEAR(date_submitted) = YEAR(now())
			QUERY
		);
		$article->setData('CodigoArtigo', $result->GetRowAssoc(false)['code']);


		return false;
	}


	/**
	 * Init article Campo1
	 */
 	function metadataInitData($hookName, $params) {
		$form =& $params[0];
		$article = $form->submission;
		$this->sectionId = $article->getData('sectionId');
		$form->setData('conflitoInteresse', $article->getData('conflitoInteresse'));
		//$form->setData('conflitoInteresseQual', $article->getData('conflitoInteresseQual'));
		//$form->setData('FonteFinanciamento', $article->getData('FonteFinanciamento'));
		//$form->setData('FonteFinanciamentoQual', $article->getData('FonteFinanciamentoQual'));
		$form->setData('agradecimentos', $article->getData('agradecimentos'));
		$form->setData('codigoTematico', $article->getData('codigoTematico'));
		$form->setData('Tema', $article->getData('Tema'));
		$form->setData('codigoArtigoRelacionado', $article->getData('codigoArtigoRelacionado'));
		//$form->setData('doi', $article->getData('doi'));

		return false;
	}


	function publicationEdit($hookName, $params) {
		$params[0]->setData('agradecimentos', $params[3]->_requestVars["agradecimentos"]);
		$params[1]->setData('agradecimentos', $params[3]->_requestVars["agradecimentos"]);
		$params[2]["agradecimentos"] = $params[3]->_requestVars["agradecimentos"];

		//$params[0]->setData('doi', $params[3]->_requestVars["doi"]);
		//$params[1]->setData('doi', $params[3]->_requestVars["doi"]);
		//$params[2]["doi"] = $params[3]->_requestVars["doi"];

		$params[0]->setData('codigoTematico', $params[3]->_requestVars["codigoTematico"]);
		$params[1]->setData('codigoTematico', $params[3]->_requestVars["codigoTematico"]);
		$params[2]["codigoTematico"] = $params[3]->_requestVars["codigoTematico"];

		$params[0]->setData('codigoArtigoRelacionado', $params[3]->_requestVars["codigoArtigoRelacionado"]);
		$params[1]->setData('codigoArtigoRelacionado', $params[3]->_requestVars["codigoArtigoRelacionado"]);
		$params[2]["codigoArtigoRelacionado"] = $params[3]->_requestVars["codigoArtigoRelacionado"];

		$params[0]->setData('conflitoInteresse', $params[3]->_requestVars["conflitoInteresse"]);
		$params[1]->setData('conflitoInteresse', $params[3]->_requestVars["conflitoInteresse"]);
		$params[2]["conflitoInteresse"] = $params[3]->_requestVars["conflitoInteresse"];

		return false;
	}
	/**
	 * Add check/validation for the Campo1 field (= 6 numbers)
	 */
	function addCheck($hookName, $params) {
		$form =& $params[0];

		if($this->sectionId == 4){
			$form->addCheck(new FormValidatorLength($form, 'codigoTematico', 'required', 'plugins.generic.CspSubmission.codigoTematico.Valid', '>', 0));
			$form->addCheck(new FormValidatorLength($form, 'Tema', 'required', 'plugins.generic.CspSubmission.Tema.Valid', '>', 0));
		}

		if($this->sectionId == 6){
			$form->addCheck(new FormValidatorLength($form, 'codigoArtigoRelacionado', 'required', 'plugins.generic.CspSubmission.codigoArtigoRelacionado.Valid', '>', 0));
		}

		$form->addCheck(new FormValidatorCustom($form, 'source', 'optional', 'plugins.generic.CspSubmission.doi.Valid', function($doi) {
			if (!filter_var($doi, FILTER_VALIDATE_URL)) {
				if (strpos(reset($doi), 'doi.org') === false){
					$doi = 'http://dx.doi.org/'.reset($doi);
				} elseif (strpos(reset($doi),'http') === false) {
					$doi = 'http://'.reset($doi);
				} else {
					return false;
				}
			}

			$client = HttpClient::create();
			$response = $client->request('GET', $doi);
			$statusCode = $response->getStatusCode();
			return in_array($statusCode,[303,200]);
		}));


		return false;
	}

	public function submissionfilesuploadformValidate($hookName, $args) {
		// Retorna o tipo do arquivo enviado
		$genreId = $args[0]->getData('genreId');
		$args[0]->_data["fileStage"];
		switch($genreId) {
			case 1: // Corpo do artigo
			case 13: // Tabela ou quadro
			case 19: // Nova versão corpo
			case 20: // Nova versão tabela ou quadro
				if (($_FILES['uploadedFile']['type'] <> 'application/msword') /*Doc*/
				and ($_FILES['uploadedFile']['type'] <> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') /*docx*/
				and ($_FILES['uploadedFile']['type'] <> 'application/vnd.oasis.opendocument.text')/*odt*/) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.AticleBody')
					);
					break;
				}

				$submissionDAO = Application::getSubmissionDAO();
				$request = \Application::get()->getRequest();
				/** @val Submission */
				$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
				$publication = $submission->getCurrentPublication();
				$sectionId = $publication->getData('sectionId');
				$sectionDAO = DAORegistry::getDAO('SectionDAO');
				$section = $sectionDAO->getById($sectionId);

				$formato = explode('.', $_FILES['uploadedFile']['name']);
				$formato = trim(strtolower(end($formato)));

				$readers = array('docx' => 'Word2007', 'odt' => 'ODText', 'rtf' => 'RTF', 'doc' => 'ODText');
				$doc = \PhpOffice\PhpWord\IOFactory::load($_FILES['uploadedFile']['tmp_name'], $readers[$formato]);
				$html = new PhpOffice\PhpWord\Writer\HTML($doc);
				$contagemPalavras = str_word_count(strip_tags($html->getWriterPart('Body')->write()));

				switch($sectionId) {
					case 1: // Artigo
					case 4: // Debate
					case 8: //  Questões Metodológicas
					case 10: // Entrevista
						if ($contagemPalavras > 6000) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 6000,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 2: // Editorial
					case 9: // Comunicação breve
						if ($contagemPalavras > 2000) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 2000,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 3: // Perspectivas
						if ($contagemPalavras > 2200) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 2200,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 6: // Revisão
					case 7: // Ensaio
						if ($contagemPalavras > 8000) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 8000,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 5: // Espaço Temático
						if ($contagemPalavras > 4000) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 4000,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 11: // Carta
					case 15: // Comentários
						if ($contagemPalavras > 1300) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 1300,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 12: // Resenhas
						if ($contagemPalavras > 1300) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 1300,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 13: // Obtuário
						if ($contagemPalavras > 1000) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 1000,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 14: // Errata
						if ($contagemPalavras > 700) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 700,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
				}
			break;
			case 10: // Figura
			case 22: // Nova versão Figura
				if (!in_array($_FILES['uploadedFile']['type'], ['image/bmp', 'image/tiff', 'image/png', 'image/jpeg'])) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Image')
					);
				}
			break;
			case '46': 	// PDF para avaliação
			case '30': 	// Nova versão PDF
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$userDao = DAORegistry::getDAO('UserDAO');

				if (($_FILES['uploadedFile']['type'] <> 'application/pdf')/*PDF*/) {
					$args[0]->addError('typeId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.PDF')
					);
				}else{
					if($genreId == '46'){ // QUANDO SECRETARIA SOBRE UM PDF NO ESTÁGIO DE SUBMISSÃO, A SUBMISSÃO É DESIGNADA PARA TODOS OS EDITORES DA REVISTA

						$result = $userDao->retrieve(
							<<<QUERY
							SELECT s.user_group_id , g.user_id, a.user_id as assigned
							FROM ojs.user_user_groups g
							LEFT JOIN ojs.user_group_settings s
							ON s.user_group_id = g.user_group_id
							LEFT JOIN ojs.stage_assignments a
							ON g.user_id = a.user_id AND a.submission_id = $submissionId
							WHERE s.setting_value = 'Editor da revista'
							QUERY
						);
						while (!$result->EOF) {

							if($result->GetRowAssoc(0)['assigned'] == NULL){

								$userGroupId = $result->GetRowAssoc(0)['user_group_id'];
								$userId = $result->GetRowAssoc(0)['user_id'];

								$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
								$stageAssignment = $stageAssignmentDao->newDataObject();
								$stageAssignment->setSubmissionId($submissionId);
								$stageAssignment->setUserGroupId($userGroupId);
								$stageAssignment->setUserId($userId);
								$stageAssignment->setRecommendOnly(0);
								$stageAssignment->setCanChangeMetadata(1);
								$stageAssignmentDao->insertObject($stageAssignment);

								$submissionDAO = Application::getSubmissionDAO();
								$submission = $submissionDAO->getById($submissionId);

								$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
								$assignedUser = $userDao->getById($userId);
								$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
								$userGroup = $userGroupDao->getById($userGroupId);

								import('lib.pkp.classes.log.SubmissionLog');
								SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_ADD_PARTICIPANT, 'submission.event.participantAdded', array('name' => $assignedUser->getFullName(), 'username' => $assignedUser->getUsername(), 'userGroupName' => $userGroup->getLocalizedName()));

							}

							$result->MoveNext();
						}
					}
					if($genreId == 30){ // QUANDO SECRETARIA SOBE UM PDF NO ESTÁGIO DE AVALIAÇÃO, O EDITOR ASSOCIADO É NOTIFICADO
						$stageId = $request->getUserVar('stageId');
						$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
						$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 5);

						import('lib.pkp.classes.mail.MailTemplate');

						while ($user = $users->next()) {

							$mail = new MailTemplate('AVALIACAO_AUTOR_EDITOR_ASSOC');
							$mail->addRecipient($user->getEmail(), $user->getFullName());

							if (!$mail->send()) {
								import('classes.notification.NotificationManager');
								$notificationMgr = new NotificationManager();
								$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
							}
						}
					}
				}
			break;
			// Quando revisor/tradutor faz upload de arquivo no box de arquivo para edição de texto, editores assistentes são notificados
			case '48': // DE rev-trad corpo PT
			case '49': // DE rev-trad corpo  EN
			case '50': // DE rev-trad corpo  ES
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$stageId = $request->getUserVar('stageId');
				$locale = AppLocale::getLocale();

				import('lib.pkp.classes.file.SubmissionFileManager');

				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
				$submissionFiles = $submissionFileDao->getBySubmissionId($submissionId);
				foreach ($submissionFiles as $submissionFile) {
					$genreIds[] = $submissionFile->_data["genreId"];
				}

				$genreIdsRevTrad = array(48,49,50);

				if(empty(array_intersect($genreIds, $genreIdsRevTrad))){

					import('lib.pkp.classes.mail.MailTemplate');

					$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
					$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 24);
					while ($user = $users->next()) {

						$mail = new MailTemplate('COPYEDIT_RESPONSE');
						$mail->addRecipient($user->getEmail(), $user->getFullName());

						if (!$mail->send()) {
							import('classes.notification.NotificationManager');
							$notificationMgr = new NotificationManager();
							$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
						}
					}
				}


			break;
			// Quando revisor de figura faz upload de figura alterada no box arquivos para edição de texto
			case '54': // Figura alterada
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$stageId = $request->getUserVar('stageId');
				$locale = AppLocale::getLocale();

				import('lib.pkp.classes.mail.MailTemplate');

				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 24);
				while ($user = $users->next()) {

					$mail = new MailTemplate('EDICAO_TEXTO_FIG_APROVD');
					$mail->addRecipient($user->getEmail(), $user->getFullName());

					if (!$mail->send()) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
					}
				}
			break;
			// Quando revisor de figura faz upload de figura formatada no box arquivos para edição de texto
			case '64': // Figura formatada
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$stageId = $request->getUserVar('stageId');
				$locale = AppLocale::getLocale();

				import('lib.pkp.classes.mail.MailTemplate');

				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 24);
				while ($user = $users->next()) {

					$mail = new MailTemplate('EDITORACAO_FIG_FORMATADA');
					$mail->addRecipient($user->getEmail(), $user->getFullName());

					if (!$mail->send()) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
					}
				}
			break;
			case '':
				$args[0]->setData('genreId',47);
				$args[1] = true;
			break;
		return true;
		}

		if (!defined('SESSION_DISABLE_INIT')) {
			$request = \Application::get()->getRequest();
			$user = $request->getUser();

			if (!$args[0]->isValid() && $user) {
				import('classes.notification.NotificationManager');
				$notificationManager = new NotificationManager();
				$notificationManager->createTrivialNotification(
					$user->getId(),
					NOTIFICATION_TYPE_FORM_ERROR,
					['contents' => $args[0]->getErrorsArray()]
				);
			}
		}
		if (!$args[0]->isValid()) {
			return true;
		}
		return false;
	}

	public function SubmissionHandler_saveSubmit($hookName, $args)
	{
		$this->article = $args[1];
	}

	function fileManager_downloadFile($hookName, $args)
	{
		list($filePath, $mediaType, $inline, $result, $fileName) = $args;
		if (is_readable($filePath)) {
			if ($mediaType === null) {
				// If the media type wasn't specified, try to detect.
				$mediaType = PKPString::mime_content_type($filePath);
				if (empty($mediaType)) $mediaType = 'application/octet-stream';
			}
			if ($fileName === null) {
				// If the filename wasn't specified, use the server-side.
				$fileName = basename($filePath);
			}
			preg_match('/\/articles\/(?P<id>\d+)\//',$filePath,$matches);
			if ($matches) {
				$submissionDao = DAORegistry::getDAO('SubmissionFileDAO');
				$result = $submissionDao->retrieve(
					<<<QUERY
					SELECT REPLACE(setting_value,'/','_') AS codigo_artigo
					FROM ojs.submission_settings
					WHERE setting_name = 'CodigoArtigo' AND submission_id = ?
					QUERY,
					[$matches['id']]
				);
				$a = $result->GetRowAssoc(false);
				$fileName = $a['codigo_artigo'].'_'.$fileName;
			}
			// Stream the file to the end user.
			header("Content-Type: $mediaType");
			header('Content-Length: ' . filesize($filePath));
			header('Accept-Ranges: none');
			header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"$fileName\"");
			header('Cache-Control: private'); // Workarounds for IE weirdness
			header('Pragma: public');
			FileManager::readFileFromPath($filePath, true);
			$returner = true;
		} else {
			$returner = false;
		}
		HookRegistry::call('FileManager::downloadFileFinished', array(&$returner));
		return true;
	}
}
