<?php
namespace HtImgModule\Service;

use Imagine\Image\ImagineInterface;
use HtImgModule\Options\CacheOptionsInterface;
use Zend\View\Resolver\ResolverInterface;
use HtImgModule\Imagine\Filter\FilterManagerInterface;
use HtImgModule\Exception;

class ImageService implements ImageServiceInterface
{
    /**
     * @var CacheOptionsInterface
     */
    protected $cacheOptions;

    /**
     * @var CacheManagerInterface
     */
    protected $cacheManager;

    /**
     * @var ImagineInterface
     */
    protected $imagine;

    /**
     * @var ResolverInterface
     */
    protected $relativePathResolver;

    /**
     * @var FilterManagerInterface
     */
    protected $filterManager;

    /**
     * Constructor
     *
     * @param CacheManagerInterface  $cacheManager
     * @param CacheOptionsInterface  $cacheOptions
     * @param ImagineInterface       $imagine
     * @param FilterManagerInterface $filterManager
     */
    public function __construct(
        CacheManagerInterface $cacheManager,
        CacheOptionsInterface $cacheOptions,
        ImagineInterface $imagine,
        ResolverInterface $relativePathResolver,
        FilterManagerInterface $filterManager
    )
    {
        $this->cacheManager = $cacheManager;
        $this->cacheOptions = $cacheOptions;
        $this->imagine = $imagine;
        $this->filterManager = $filterManager;
        $this->relativePathResolver = $relativePathResolver;
    }

    /**
     * {@inheritDoc}
     */
    public function getImageFromRelativePath($relativePath, $filter)
    {
        $filterOptions = $this->filterManager->getFilterOptions($filter);

        if (isset($filterOptions['format'])) {
            $format = $filterOptions['format'];
        } else {
            $imagePath = $this->relativePathResolver->resolve($relativePath);
            $format = pathinfo($imagePath, PATHINFO_EXTENSION);
            $format = $format ?: 'png';
        }
        if ($this->cacheOptions->getEnableCache() && $this->cacheManager->cacheExists($relativePath, $filter, $format)) {
            $imagePath = $this->cacheManager->getCachePath($relativePath, $filter, $format);
            $image = $this->imagine->open($imagePath);
        } else {
            if (!isset($imagePath)) {
                $imagePath = $this->relativePathResolver->resolve($relativePath);
            }
            if (!$imagePath) {
                throw new Exception\ImageNotFoundException(
                    sprintf('Unable to resolve %s', $relativePath)
                );
            }
            $image = $this->getImage($imagePath, $filter);
            if ($this->cacheOptions->getEnableCache()) {
                $this->cacheManager->createCache($relativePath, $filter, $image, $format);
            }
        }

        return [
            'image' => $image,
            'format' => $format
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getImage($imagePath, $filter)
    {
        $image = $this->imagine->open($imagePath);

        return $this->filterManager->getFilter($filter)->apply($image);
    }
}
