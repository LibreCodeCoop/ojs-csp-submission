<?php

import('plugins.generic.cspSubmission.class.AbstractPlugin');
import('plugins.generic.paperbuzz.PaperbuzzPlugin');

/**
 * @file plugins/generic/cspSubmission/class/TemplatesCsp.inc.php
 *
 * @class TemplatesCsp
 *
 * @brief Class for modify templates
 *
 */
class TemplatesCsp extends AbstractPlugin
{
	public function articleMain($params){
		$smarty =& $params[1];
		$output =& $params[2];
		$paperbuzz = PluginRegistry::getPlugin('generic', 'PaperbuzzPlugin');
		
		$article = $smarty->getTemplateVars('article');
		$paperbuzz->_article = $article;

		$publishedPublications = (array) $article->getPublishedPublications();
		$firstPublication = reset($publishedPublications);

		$request = Registry::get('request');
		$context = $request->getContext();

		$paperbuzzJsonDecoded = $this->_getPaperbuzzJsonDecoded();
		$downloadJsonDecoded = array();
		if (!$paperbuzz->getSetting($context->getId(), 'hideDownloads')) {
			$downloadJsonDecoded = $paperbuzz->_getDownloadsJsonDecoded();
		}

		if (!empty($downloadJsonDecoded) || !empty($paperbuzzJsonDecoded)) {
			$allStatsJson = $paperbuzz->_buildRequiredJson($paperbuzzJsonDecoded, $downloadJsonDecoded);
			$smarty->assign('allStatsJson', $allStatsJson);

			if (!empty($firstPublication->getData('datePublished'))) {
				$datePublishedShort = date('[Y, n, j]', strtotime($firstPublication->getData('datePublished')));
				$smarty->assign('datePublished', $datePublishedShort);
			}

			$showMini = $paperbuzz->getSetting($context->getId(), 'showMini') ? 'true' : 'false';
			$smarty->assign('showMini', $showMini);
			$metricsHTML = $smarty->fetch($paperbuzz->getTemplateResource('output.tpl'));
			$output .= $metricsHTML;
		}

		return false;
	}

	//
	// Private helper methods.
	//
	/**
	 * Get Paperbuzz events for the article.
	 * @return string JSON message
	 */
	function _getPaperbuzzJsonDecoded() {
		if (!isset($paperbuzz->_paperbuzzCache)) {
			$paperbuzz = PluginRegistry::getPlugin('generic', 'PaperbuzzPlugin');

			$cacheManager = CacheManager::getManager();
			$paperbuzz->_paperbuzzCache = $cacheManager->getCache('paperbuzz', $paperbuzz->_article->getId(), array(&$this, '_paperbuzzCacheMiss'));
		}
		if (time() - $paperbuzz->_paperbuzzCache->getCacheTime() > 60 * 60 * 24) {
			// Cache is older than one day, erase it.
			$paperbuzz->_paperbuzzCache->flush();
		}
		$cacheContent = $paperbuzz->_paperbuzzCache->getContents();
		return $cacheContent;
	}

	/**
	* Cache miss callback.
	* @param $cache Cache
	* @param $articleId int
	* @return JSON
	*/
	function _paperbuzzCacheMiss($cache, $articleId) {
		$paperbuzz = PluginRegistry::getPlugin('generic', 'PaperbuzzPlugin');

		$request = $paperbuzz->getRequest();
		$context = $request->getContext();
		$apiEmail = $paperbuzz->getSetting($context->getId(), 'apiEmail');

		$paperbuzz->_article->_data["publications"][0]->_data["pub-id::doi"] = $paperbuzz->_article->_data["publications"][0]->getLocalizedData("pub-id::doi");

		//$url = PAPERBUZZ_API_URL . 'doi/' . $this->_article->getStoredPubId('doi') . '?email=' . urlencode($apiEmail);
		$url = PAPERBUZZ_API_URL . 'doi/' . $paperbuzz->_article->_data["publications"][0]->getLocalizedData("pub-id::doi") . '?email=' . urlencode($apiEmail);
		// For teting use one of the following two lines instead of the line above and do not forget to clear the cache
		// $url = PAPERBUZZ_API_URL . 'doi/10.1787/180d80ad-en?email=' . urlencode($apiEmail);
		// $url = PAPERBUZZ_API_URL . 'doi/10.1371/journal.pmed.0020124?email=' . urlencode($apiEmail);

		$paperbuzzStatsJsonDecoded = array();
		$httpClient = Application::get()->getHttpClient();
		try {
			$response = $httpClient->request('GET', $url);
		} catch (GuzzleHttp\Exception\RequestException $e) {
			return $paperbuzzStatsJsonDecoded;
		}
		$resultJson = $response->getBody()->getContents();
		if ($resultJson) {
			$paperbuzzStatsJsonDecoded = @json_decode($resultJson, true);
		}
		$cache->setEntireCache($paperbuzzStatsJsonDecoded);
		return $paperbuzzStatsJsonDecoded;
	}


}
