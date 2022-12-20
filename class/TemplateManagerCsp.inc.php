<?php
import('plugins.generic.cspSubmission.class.AbstractPlugin');

class TemplateManagerCsp extends AbstractPlugin {

	public function display($args) {
		$request =& Registry::get('request');
		$templateManager =& $args[0];

		$templateManager->addJavaScript(
			'coautor',
			$request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath() . '/js/build.js',
			array(
				'contexts' => 'backend',
				'priority' => STYLE_SEQUENCE_LAST,
			)
		);
		$templateManager->addStyleSheet(
			'coautor',
			$request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath() . '/styles/build.css',
			array(
				'contexts' => 'backend',
				'priority' => STYLE_SEQUENCE_LAST,
			)
		);
		if(strpos($args[1], "quickSubmit") && $request->getUserVar('articleStatus') == 0){
			$args[1] = '../plugins/generic/cspSubmission/templates/quickSubmitIndex.tpl';
			$templateManager->assign('abstractDisplay', true);
			$templateManager->assign('sourceEnabled', false);
			$templateManager->assign('agenciesEnabled', false);
			$templateManager->assign('subjectsEnabled', false);

		}
		if ($args[1] == "workflow/workflow.tpl" or $args[1] == "authorDashboard/authorDashboard.tpl") {
			$path = $request->getRequestPath();
			$pathItens = explode('/', $path);
			$submissionId = $pathItens[6];
			$submissionDAO = Application::getSubmissionDAO();
			$submission = $submissionDAO->getById($submissionId);
			$publication = $submission->getCurrentPublication();
			$sectionId = $publication->getData('sectionId');
			$sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
			$section = $sectionDao->getById($sectionId);
			$sectionLocalizedTitle = $section->getLocalizedTitle();
			$templateManager->assign('sectionLocalizedTitle', $sectionLocalizedTitle);
		}
		if ($args[1] == "dashboard/index.tpl") {
			if(!$request->getUserVar('substage')){
				$currentUser = $request->getUser();
				$context = $request->getContext();
				$stages = array();
				$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO'); /* @var $userGroupAssignmentDao UserGroupAssignmentDAO */
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
				$assignedGroups = $userGroupAssignmentDao->getByUserId($currentUser->getData('id'), $context->getId());
				while ($assignedGroup = $assignedGroups->next()) {
					$userGroup = $userGroupDao->getById($assignedGroup->getUserGroupId());
					$userGroupsAbbrev[] = $userGroup->getLocalizedAbbrev();
				}
				$requestRoleAbbrev = $request->getUserVar('requestRoleAbbrev');
				$sessionManager = SessionManager::getManager();
				$session = $sessionManager->getUserSession();
				if($requestRoleAbbrev){
					$session->setSessionVar('role', $requestRoleAbbrev);
				}
				$role = $session->getSessionVar('role');

				if ($role == 'Ed. chefe' or $role == 'Gerente') {
					$stages['Pré-avaliação']["'pre_aguardando_editor_chefe'"][1] = "Aguardando decisão (" .$this->countStatus("'pre_aguardando_editor_chefe'",date('Y-m-d H:i:s'),1).")";
					$stages['Avaliação']["'ava_consulta_editor_chefe'"][1] = "Consulta ao editor chefe (" .$this->countStatus("'ava_consulta_editor_chefe'",date('Y-m-d H:i:s'),1).")";
				}
				if ($role == 'Ed. associado' or $role == 'Gerente') {
					$stages['Avaliação']["'ava_aguardando_editor_chefe'"][1] = "Aguardando decisão da editoria (" .$this->countStatus("'ava_aguardando_editor_chefe'",date('Y-m-d H:i:s'),1).")";
					$status = "'ava_com_editor_associado','ava_aguardando_avaliacao'";
					$stages['Avaliação'][$status][1] = "Com o editor associado (" .$this->countStatus($status,date('Y-m-d H:i:s'),1).")";
					$stages['Avaliação']["'ava_aguardando_autor'"][1] = "Aguardando autor (" .$this->countStatus("'ava_aguardando_autor'",date('Y-m-d H:i:s'),1).")";
					$stages['Avaliação']["'ava_aguardando_autor_mais_60_dias'"][1] = "Há mais de 60 dias com o autor (" .$this->countStatus("'ava_aguardando_autor'",date('Y-m-d H:i:s', strtotime('-2 months')),1).")";
					$stages['Avaliação']["'ava_aguardando_secretaria'"][1] = "Aguardando secretaria (" .$this->countStatus("'ava_aguardando_secretaria'",date('Y-m-d H:i:s'),1).")";
				}
				if ($role == 'Avaliador') {
					$stages['Avaliação']["'ava_aguardando_avaliacao'"][1] = "Aguardando avaliacao (" .$this->countStatus("'ava_aguardando_avaliacao'",date('Y-m-d H:i:s'),1).")";
				}
				if ($role == 'Secretaria' or $role == 'Gerente') {
					$stages['Pré-avaliação']["'pre_aguardando_secretaria'"][1] = "Aguardando secretaria (" .$this->countStatus("'pre_aguardando_secretaria'",date('Y-m-d H:i:s'),1).")";
					$stages['Pré-avaliação']["'pre_pendencia_tecnica'"][1] = "Pendência técnica (" .$this->countStatus("'pre_pendencia_tecnica'",date('Y-m-d H:i:s'),1).")";
					$stages['Avaliação']["'ava_aguardando_autor_mais_60_dias'"][1] = "Há mais de 60 dias com o autor (" .$this->countStatus("'ava_aguardando_autor'",date('Y-m-d H:i:s', strtotime('-2 months')),1).")";
					$stages['Avaliação']["'ava_aguardando_secretaria'"][1] = "Aguardando secretaria (" .$this->countStatus("'ava_aguardando_secretaria'",date('Y-m-d H:i:s'),1).")";
				}
				if ($role == 'Autor') {
					$stages['Pré-avaliação']["'em_progresso'"][1] = "Em progresso (".$this->countStatus("'em_progresso'",date('Y-m-d H:i:s'),1).")";
					$status = "'pre_aguardando_secretaria','pre_aguardando_editor_chefe'";
					$stages['Pré-avaliação'][$status][1] = "Submetidas (".$this->countStatus($status,date('Y-m-d H:i:s'),1).")";
					$stages['Pré-avaliação']["'pre_pendencia_tecnica'"][1] = "Pendência técnica (" .$this->countStatus("'pre_pendencia_tecnica'",date('Y-m-d H:i:s'),1).")";
					$status = "'ava_aguardando_editor_chefe','ava_consulta_editor_chefe','ava_com_editor_associado','ava_aguardando_secretaria'";
					$stages['Avaliação'][$status][1] = "Em avaliação (" .$this->countStatus($status,date('Y-m-d H:i:s'),1).")";
					$stages['Avaliação']["'ava_aguardando_autor'"][1] = "Modificações solicitadas (" .$this->countStatus("'ava_aguardando_autor'",date('Y-m-d H:i:s'),1).")";
					$status = "'ed_text_envio_carta_aprovacao','ed_text_para_revisao_traducao','ed_text_em_revisao_traducao','ed_texto_traducao_metadados','edit_aguardando_padronizador','edit_pdf_padronizado','edit_em_prova_prelo','ed_text_em_avaliacao_ilustracao','edit_em_formatacao_figura','edit_em_diagramacao','edit_aguardando_publicacao'";
					$stages['Pós-avaliação'][$status][1] = "Aprovadas (" .$this->countStatus($status,date('Y-m-d H:i:s'),1).")";
				}
				if ($role == 'Ed. assistente' or $role == 'Gerente' or $role == 'Revisor - Tradutor') {
					$stages['Edição de texto']["'ed_text_envio_carta_aprovacao'"][1] = "Envio de Carta de aprovação (" .$this->countStatus("'ed_text_envio_carta_aprovacao'",date('Y-m-d H:i:s'),1).")";
					$stages['Edição de texto']["'ed_text_para_revisao_traducao'"][1] = "Para revisão/Tradução (" .$this->countStatus("'ed_text_para_revisao_traducao'",date('Y-m-d H:i:s'),1).")";
					$stages['Edição de texto']["'ed_text_em_revisao_traducao'"][1] = "Em revisão/Tradução (" .$this->countStatus("'ed_text_em_revisao_traducao'",date('Y-m-d H:i:s'),1).")";
					$stages['Edição de texto']["'ed_texto_traducao_metadados'"][1] = "Tradução de metadados (" .$this->countStatus("'ed_texto_traducao_metadados'",date('Y-m-d H:i:s'),1).")";
				}
				if ($role == 'Ed. assistente' or $role == 'Gerente') {
					$stages['Editoração']["'edit_aguardando_padronizador'"][1] = "Aguardando padronizador (" .$this->countStatus("'edit_aguardando_padronizador'",date('Y-m-d H:i:s'),1).")";
					$stages['Editoração']["'edit_pdf_padronizado'"][1] = "PDF padronizado (" .$this->countStatus("'edit_pdf_padronizado'",date('Y-m-d H:i:s'),1).")";
					$stages['Editoração']["'edit_em_prova_prelo'"][1] = "Em prova de prelo (" .$this->countStatus("'edit_em_prova_prelo'",date('Y-m-d H:i:s'),1).")";
				}
				if ($role == 'Ed. Layout' or $role == 'Gerente') {
					$stages['Edição de texto']["'ed_text_em_avaliacao_ilustracao'"][1] = "Em avaliação de ilustração (" .$this->countStatus("'ed_text_em_avaliacao_ilustracao'",date('Y-m-d H:i:s'),1).")";
					$stages['Editoração']["'edit_em_formatacao_figura'"][1] = "Em formatação de Figura (" .$this->countStatus("'edit_em_formatacao_figura'",date('Y-m-d H:i:s'),1).")";
					$stages['Editoração']["'edit_em_diagramacao'"][1] = "Em diagramação (" .$this->countStatus("'edit_em_diagramacao'",date('Y-m-d H:i:s'),1).")";
					$stages['Editoração']["'edit_aguardando_publicacao'"][1] = "Aguardando publicação (" .$this->countStatus("'edit_aguardando_publicacao'",date('Y-m-d H:i:s'),1).")";
				}
				if ($role == 'Ed. Layout' or $role == 'Gerente') {
					$stages['Edição de texto']["'ed_text_em_avaliacao_ilustracao'"][1] = "Em avaliação de ilustração (" .$this->countStatus("'ed_text_em_avaliacao_ilustracao'",date('Y-m-d H:i:s'),1).")";
					$stages['Editoração']["'edit_em_formatacao_figura'"][1] = "Em formatação de Figura (" .$this->countStatus("'edit_em_formatacao_figura'",date('Y-m-d H:i:s'),1).")";
					$stages['Editoração']["'edit_em_diagramacao'"][1] = "Em diagramação (" .$this->countStatus("'edit_em_diagramacao'",date('Y-m-d H:i:s'),1).")";
					$stages['Editoração']["'edit_aguardando_publicacao'"][1] = "Aguardando publicação (" .$this->countStatus("'edit_aguardando_publicacao'",date('Y-m-d H:i:s'),1).")";
				}
				if($role){
					$stages['Finalizadas']["'publicada'"][3] = "Publicadas (" .$this->countStatus("'publicada'",date('Y-m-d H:i:s'),3).")";
					$stages['Finalizadas']["'rejeitada'"][4] = "Rejeitadas (" .$this->countStatus("'rejeitada'",date('Y-m-d H:i:s'),4).")";
					$stages['Finalizadas']["'fin_consulta_editor_chefe'"][4] = "Consulta a Ed. Chefe (" .$this->countStatus("'fin_consulta_editor_chefe'",date('Y-m-d H:i:s'),4).")";
				}
				$array_sort = array('pre_aguardando_secretaria',
									'pre_pendencia_tecnica',
									'pre_aguardando_editor_chefe',
									'ava_com_editor_associado',
									'ava_aguardando_autor',
									'ava_aguardando_autor_mais_60_dias',
									'ava_aguardando_secretaria',
									'ava_aguardando_editor_chefe',
									'ava_consulta_editor_chefe',
									'ed_text_em_avaliacao_ilustracao',
									'ed_text_envio_carta_aprovacao',
									'ed_text_para_revisao_traducao',
									'ed_text_em_revisao_traducao',
									'ed_texto_traducao_metadados',
									'edit_aguardando_padronizador',
									'edit_em_formatacao_figura',
									'edit_em_prova_prelo',
									'edit_pdf_padronizado',
									'edit_em_diagramacao',
									'edit_aguardando_publicacao',
									'publicada',
									'rejeitada'
								);
				$templateManager->assign('basejs', $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath() . '/js/build.js');
				$templateManager->assign('basecss', $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath() . '/styles/build.css');

			}else{
				// // Load JavaScript file
				$templateManager->addJavaScript(
					'csp',
					$request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath() . '/js/build.js',
					array(
						'contexts' => 'backend',
						'priority' => STYLE_SEQUENCE_LAST,
					)
				);
				$templateManager->addStyleSheet(
					'csp',
					$request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath() . '/styles/build.css',
					array(
						'contexts' => 'backend',
						'priority' => STYLE_SEQUENCE_LAST,
					)
				);
			}
			if(in_array('Autor',$userGroupsAbbrev) == False){
				$userGroupsAbbrev[] = 'Autor';
			}
			$templateManager->assign(array(
				'userGroupsAbbrev' => array_unique($userGroupsAbbrev),
				'stages' => $stages,
				'substage' => $request->getUserVar('substage'),
				'requestRoleAbbrev' => $role,
				'array_sort' => array_flip($array_sort)
			));

			$args[1] = '../plugins/generic/cspSubmission/templates/dashboard.tpl';
		}
		return false;
	}

	public function countStatus($subStage, $date, $status){
		$request =& Registry::get('request');
		$context = $request->getContext();
		$contextId = $context->getData('id');
		$currentUser = $request->getUser();
		$currentUserId = $currentUser->getData('id');
		$sessionManager = SessionManager::getManager();
		$session = $sessionManager->getUserSession();
		$role = $session->getSessionVar('role');

		$userDao = DAORegistry::getDAO('UserDAO');
		$sql = "select count(DISTINCT s.submission_id) as contador
		from
			submissions as s
		left join stage_assignments as sa on
			s.submission_id = sa.submission_id
		left join review_assignments as ra on
			s.submission_id = ra.submission_id
		where s.context_id = $contextId
		and (sa.stage_assignment_id is not null or ra.review_id is not null)
		and s.status = $status";

		if($role == 'Avaliador'){
			$sql .= " and ra.reviewer_id = $currentUserId and ra.declined = 0 and ra.date_completed IS NULL";
		}elseif($role <> "Gerente"){
			$sql .= " and sa.user_id = $currentUserId";
			if($role == "Autor"){
				$sql .= " and sa.user_group_id = 14";
			}
		}
		$sql .= " and s.submission_id in (select DISTINCT csp_status.submission_id
											from csp_status
											where csp_status.status in (".trim($subStage).")
											and date_status <= '$date')";

		$result = $userDao->retrieve($sql);
		$count = $result->current();
		return $count->contador;
	}
}