<?php
/*
 * This file is part of the AlphaLemonThemeEngineBundle and it is distributed
 * under the MIT License. In addiction, to use this bundle, you must leave
 * intact this copyright notice.
 *
 * (c) Since 2011 AlphaLemon
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://alphalemon.com
 * 
 * @license    MIT License
 * 
 */

namespace AlphaLemon\ThemeEngineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Yaml\Yaml;
use ThemeEngineCore\Model\AlThemeQuery;
use ThemeEngineCore\Manager\Theme\AlThemeManager;
use ThemeEngineCore\Event\PageRenderer\BeforePageRenderingEvent;
use ThemeEngineCore\Event\PageRendererEvents;
use PageTreeCore\PageTree\AlPageTree;

class TemplateController extends Controller
{   
    protected $theme = null;

    /**
     * Renders the page from the current pagetree object
     * 
     * @return Response A response instance
     */
    protected function doRender()
    {
        $pageTree = $this->container->get('al_page_tree');
        
        $template = sprintf('%s:Theme:%s.html.twig', $pageTree->getThemeName(), $pageTree->getTemplateName());        
        return $this->render($template, array('metatitle' => ($pageTree->getMetaTitle() == '') ? 'A site powered by AlphaLemon ThemeEngineBundle' : $pageTree->getMetaTitle(),
                                              'metadescription' => $pageTree->getMetaDescription(),
                                              'metakeywords' => $pageTree->getMetaKeywords(),
                                              'internal_stylesheets' => $pageTree->getInternalStylesheet(),
                                              'internal_javascripts' => $pageTree->getInternalJavascript(),
                                              'stylesheets' => $pageTree->getExternalStylesheetsForWeb(),
                                              'javascripts' => $pageTree->getExternalJavascriptsForWeb(),
                                              'base_template' =>  $this->container->getParameter('althemes.base_template')));
    }

    /**
     * Sets up the page tree object which contains all the information about the webpage
     * 
     * @param   string  $templateName
     * @param   string  $dictionary 
     */    
    protected function setUpPageTree($templateName, $dictionary = null)
    {
        $pageTree = $this->container->get('al_page_tree');
        
        $this->theme = AlThemeQuery::create()->filterByActive(1)->findOne();
        if(null !== $this->theme)
        {
            $pageTree->setThemeName($this->theme->getThemeName());
            $pageTree->setTemplateName($templateName);       
            $templateStylesheets = sprintf('themes.%s_%s.stylesheets', preg_replace('/bundle$/', '', strtolower($this->theme->getThemeName())), strtolower($templateName));
            if($this->container->hasParameter($templateStylesheets)) $pageTree->addStylesheets($this->container->getParameter($templateStylesheets));
            $templateJavascripts = sprintf('themes.%s_%s.javascripts', preg_replace('/bundle$/', '', strtolower($this->theme->getThemeName())), strtolower($templateName));
            if($this->container->hasParameter($templateJavascripts)) $pageTree->addJavascripts($this->container->getParameter($templateJavascripts));
            if($this->container->hasParameter('themes.internal_stylesheet')) $pageTree->appendInternalStylesheet($this->container->getParameter('themes.internal_stylesheet'));
            if($this->container->hasParameter('themes.internal_javascript')) $pageTree->appendInternalJavascript($this->container->getParameter('themes.internal_javascript'));
            
            $slots = $this->retrieveSlotContents($templateName);
            $this->fillUpPageTreeContents($pageTree, $slots, $dictionary);
            
            $dispatcher = $this->container->get('event_dispatcher');            
            $event = new BeforePageRenderingEvent($this->container->get('request'), $pageTree);
            $dispatcher->dispatch(PageRendererEvents::BEFORE_RENDER_PAGE, $event);      
            $this->container->set('al_page_tree', $event->getPageTree());
        }
        else
        {
            throw new \Exception("Any theme has been loaded. Please load at least one theme to use ThemeEngine, at /en/al_showThemes, or disable it from your appKernel configuration file if you don't want to use it.");
        }
    }
    
    /**
     * Created the slots array for the current template
     * 
     * @param   string $templateName
     * @return  array 
     */
    protected function retrieveSlotContents($templateName)
    {
        $slotContentsPath = $this->container->getParameter('kernel.root_dir') . '/../' . $this->container->getParameter('althemes.slot_contents_dir');
        if(!is_dir($slotContentsPath))
        {
            throw new \InvalidArgumentException(sprintf('The directory %s does not exist. Please check that the slot_contents_dir is properly configured', $slotContentsPath));
        }

        // Retrieves the slots from the theme slotContents file
        $locator = new FileLocator($slotContentsPath);
        $fileName = 'slotContents.yml';
        $locator->locate($fileName);
        $slots = Yaml::parse($locator->locate($fileName));

        // Tries to retrieve the custom slots' contents if exists
        try
        {
            $fileName = 'slotContents.custom.yml';
            $locator->locate($fileName);
            $customSlots = Yaml::parse($locator->locate($fileName));            
        }
        catch(\InvalidArgumentException $ex)
        {
            $customSlots = null;
        }

        // Merges the predefined slots with the custom ones
        if(null !== $customSlots)
        {
            if(!array_key_exists('slots', $customSlots))
            {
                throw new \InvalidArgumentException('The slotContents.custom.yml must start with a value called slots: check your slotContents.custom.yml');
            }

            $slots['slots'] = array_merge($slots['slots'], $customSlots['slots']);
        }

        // Merges the current template's slots with the repeated ones
        $templateName = $this->theme->getThemeName() . ucfirst($templateName);
        return array_merge($slots[$templateName], $slots['slots']);
    }
    
    /**
     * Fills up the pagetree's contents from a slots array
     * 
     * @param AlPageTree    $pageTree       The pagetree object
     * @param array         $slots          The array that contains the slots' contents
     * @param type          $dictionary     The dictionary to use for the translations
     */
    protected function fillUpPageTreeContents(AlPageTree $pageTree, array $slots, $dictionary)
    {
        $request = $this->container->get('request');  
        $locale = ($request->attributes->get('_locale') != '') ? $request->attributes->get('_locale') : "it";             
        
        foreach($slots as $slotName => $contents)
        {
            $slot = $pageTree->getSlot($slotName);
            if(null !== $slot)
            {
                if(null !== $dictionary && strtolower($slot->getRepeated()) != 'site')
                {
                    foreach($contents as $content)
                    {
                        $content = $this->container->get('translator')->trans($content, array(), $dictionary, $locale); 
                        $pageTree->addContent($slotName, array('HtmlContent' => $content)); 
                    }
                }
                else
                {
                    foreach($contents as $content)
                    {
                        $pageTree->addContent($slotName, array('HtmlContent' => $content)); 
                    }
                }
            }
        }
    }
}

