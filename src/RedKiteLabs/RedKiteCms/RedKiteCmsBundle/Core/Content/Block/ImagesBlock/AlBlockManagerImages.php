<?php

namespace AlphaLemon\AlphaLemonCmsBundle\Core\Content\Block\ImagesBlock;

use AlphaLemon\AlphaLemonCmsBundle\Core\Content\Block\AlBlockManagerContainer;
use AlphaLemon\AlphaLemonCmsBundle\Core\Content\Block\JsonBlock\AlBlockManagerJsonBlock;
use AlphaLemon\AlphaLemonCmsBundle\Core\AssetsPath\AlAssetsPath;

/**
 * AlBlockManagerImages is the base object deputated to handle a content made by a list 
 * of images, like a slider or an image gallery
 *
 * @author alphalemon <webmaster@alphalemon.com>
 */
abstract class AlBlockManagerImages extends AlBlockManagerContainer
{
    /**
     * {@inheritdoc}
     *
     * Extends the base edit method to manage an images list
     * 
     * @api
     */
    protected function edit(array $values)
    {
        $values["Content"] = $this->arrangeImages($values);
        
        return $this->doBaseEdit($values);
    }
    
    protected function doBaseEdit(array $values)
    {
        return parent::edit($values);
    }

    protected function arrangeImages(array $values)
    {
        if ( ! array_key_exists('Content', $values)) {
            $images = AlBlockManagerJsonBlock::decodeJsonContent($this->alBlock);
            $savedImages = array_map(function($el){ return $el['image']; }, $images);

            if (array_key_exists('AddFile', $values)) { 
                $file = $values["AddFile"];

                $imageFile = "/" . AlAssetsPath::getUploadFolder($this->container) . "/" . preg_replace('/http?:\/\/[^\/]+/', '', $file);
                if (in_array($imageFile, $savedImages)) {
                    throw new \Exception("The image file has already been added");
                }

                $images[]['image'] = $imageFile;
            }

            if (array_key_exists('RemoveFile', $values)) { 
                $fileToRemove = $values["RemoveFile"];
                $key = array_search($fileToRemove, $savedImages);
                if (false !== $key) {
                    unset($images[$key]);
                }
            }
            
            return json_encode($images);
        }

        return $values['Content'];
    }
}
