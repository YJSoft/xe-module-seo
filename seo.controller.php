<?php
class seoController extends seo
{
	function getBrowserTitle($document_title = null)
	{
		$site_module_info = Context::get('site_module_info');
		if ($site_module_info->site_srl != 0) return Context::getBrowserTitle();

		$config = $this->getConfig();
		if ($config->use_optimize_title != 'Y') return Context::getBrowserTitle();

		$current_module_info = Context::get('current_module_info');
		$is_index = ($current_module_info->module_srl == $site_module_info->module_srl) ? true : false;

		$piece = array();
		$piece['[:site_name:]'] = $config->site_name;
		$piece['[:site_slogan:]'] = $config->site_slogan;
		$piece['[:module_title:]'] = $current_module_info->browser_title;
		if ($document_title) $piece['[:document_title:]'] = $document_title;

		if ($config->use_optimize_title == 'Y') {
			$title = array();
			if ($piece['[:document_title:]'] && !$is_index) {
				$title[] = $piece['[:document_title:]'];
				$title[] = $piece['[:module_title:]'];
				$title[] = $piece['[:site_name:]'];
			} else {
				if ($is_index) {
					$title[] = $piece['[:site_name:]'];
					if ($piece['[:site_slogan:]']) $title[] = $piece['[:site_slogan:]'];
				} else {
					$title[] = $piece['[:module_title:]'];
					$title[] = $piece['[:site_name:]'];
				}
			}
			$title = implode(' - ', $title);
		}

		return $title;
	}

	function triggerBeforeDisplay(&$output_content)
	{
		if (Context::getResponseMethod() != 'HTML') return;
		if (Context::get('module') == 'admin') return;

		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		$config = $this->getConfig();

		$logged_info = Context::get('logged_info');
		$current_module_info = Context::get('current_module_info');
		$site_module_info = Context::get('site_module_info');
		$document_srl = Context::get('document_srl');
		$is_article = false;
		$is_index = ($current_module_info->module_srl == $site_module_info->module_srl) ? true : false;

		$args->url = $current_module_info->mid;
		$args->site_srl = $site_module_info->site_srl;
		$args->is_shortcut = 'N';
		$output = executeQuery('menu.getMenuItemByUrl', $args);

		$piece = new stdClass;
		$piece->document_title = null;
		$piece->type = 'website';
		$piece->url = getFullUrl('');
		if(!$output->data->desc)
		{
			$output->data->desc = '';
		}
		$desc = explode('|', $output->data->desc);

		if($config->use_menu_desc === 'Y' && $desc[0]!='')
		{
			$oModuleController->replaceDefinedLangCode($desc[0],TRUE);
			$piece->description = htmlspecialchars($desc[0], ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		}
		else
		{
			$piece->description = $config->site_description;
		}
		$piece->title = Context::getBrowserTitle();
		$piece->keywords = $config->site_keywords;
		$piece->image = array();
		$piece->author = null;

		if ($document_srl) {
			$oDocument = Context::get('oDocument');
			if (!is_a($oDocument, 'documentItem')) {
				$oDocumentModel = getModel('document');
				$oDocument = $oDocumentModel->getDocument($document_srl);
			}

			if (is_a($oDocument, 'documentItem') && $document_srl == $oDocument->document_srl) {
				$is_article = true;
			}
		}

		// 문서 데이터 수집
		if ($is_article) {
			if (!$oDocument->isSecret()) {
				$piece->document_title = $oDocument->getTitleText();
				$piece->url = getFullUrl('', 'mid', $current_module_info->mid, 'document_srl',$document_srl);
				$piece->type = 'article';
				$piece->description = trim(str_replace('&nbsp;', ' ', $oDocument->getContentText(400)));
				$piece->author = $oDocument->getNickName();
				if (count($oDocument->get('tag_list'))) {
					$tags = implode(',', $oDocument->get('tag_list'));
					if ($tags) $piece->keywords = $tags;
				}

				if ($oDocument->hasUploadedFiles()) {
					$image_ext = array('bmp', 'gif', 'jpg', 'jpeg', 'png');
					foreach ($oDocument->getUploadedFiles() as $file) {
						if ($file->isvalid != 'Y') continue;

						$ext = array_pop(explode('.', $file->uploaded_filename));

						if (!in_array(strtolower($ext), $image_ext)) continue;
						$piece->image[] = Context::get('request_uri') . $file->uploaded_filename;
					}
				}
			} else {
				$piece->url = getFullUrl('', 'mid', $current_module_info->mid);
			}
		} else {
			if (!$is_index) {
				$page = (Context::get('page') > 1) ? Context::get('page') : null;
				$piece->url = getNotEncodedFullUrl('mid', $current_module_info->mid, 'page',$page);
			}
		}

		$piece->title = $this->getBrowserTitle($piece->document_title);
		if ($config->site_image_url) $piece->image[] = $config->site_image_url;

		$this->addLink('canonical', $piece->url);
		$this->addMeta('keywords', $piece->keywords);
		$this->addMeta('description', $piece->description);

		// Open Graph
		$this->addMeta('og:type', $piece->type);
		$this->addMeta('og:url', $piece->url);
		$this->addMeta('og:site_name', $config->site_name);
		$this->addMeta('og:title', $piece->title);
		$this->addMeta('og:description', $piece->description);
		$this->addMeta('og:article:author', $piece->author);
		foreach ($piece->image as $img) {
			$this->addMeta('og:image', $img);
		}

		$this->canonical_url = $piece->url;

		$this->applySEO();

		if ($config->use_optimize_title === 'Y') Context::setBrowserTitle($piece->title);
	}
}
/* !End of file */
