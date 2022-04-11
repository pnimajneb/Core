<?php
namespace exface\Core\Facades;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Facades\AbstractHttpFacade\NotFoundHandler;
use exface\Core\Facades\AbstractHttpFacade\HttpRequestHandler;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\DataTypes\FilePathDataType;
use Intervention\Image\ImageManager;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\DataTypes\MimeTypeDataType;
use exface\Core\DataTypes\ComparatorDataType;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;

/**
 * Facade to upload and download files using virtual pathes.
 * 
 * ## Download
 * 
 * Use the follosing url `api/files/my.App.OBJECT_ALIAS/uid` to download a file with the given `uid` value.
 * 
 * ### Image resizing
 * 
 * You can resize images by adding the URL parameter `&resize=WIDTHxHEIGHT`.
 * 
 * ## Upload
 * 
 * Not available yet
 * 
 * @author Andrej Kabachnik
 *
 */
class HttpFileServerFacade extends AbstractHttpFacade
{    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $absolutePath
     * @return string
     */
    public static function buildUrlForDownload(WorkbenchInterface $workbench, string $absolutePath, bool $relativeToSiteRoot = true)
    {
        // TODO route downloads over api/files and add an authorization point - see handle() method
        $installationPath = FilePathDataType::normalize($workbench->getInstallationPath());
        $absolutePath = FilePathDataType::normalize($absolutePath);
        if (StringDataType::startsWith($absolutePath, $installationPath) === false) {
            throw new FacadeRuntimeError('Cannot provide download link for file "' . $absolutePath . '"');
        }
        $relativePath = StringDataType::substringAfter($absolutePath, $installationPath);
        if ($relativeToSiteRoot) {
            return ltrim($relativePath, "/");
        } else {
            return $workbench->getUrl() . ltrim($relativePath, "/");
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return 'api/files';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = new HttpRequestHandler(new NotFoundHandler());
        
        // Authenticate users
        $handler->add(new AuthenticationMiddleware($this));
        
        $uri = $request->getUri();
        $path = ltrim(StringDataType::substringAfter($uri->getPath(), $this->getUrlRouteDefault()), "/");
        
        $pathParts = explode('/', $path);
        $objSel = urldecode($pathParts[0]);
        $uid = urldecode($pathParts[1]);
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $objSel);
        if (! $ds->getMetaObject()->hasUidAttribute()) {
            $this->getWorkbench()->getLogger()->logException(new FacadeRuntimeError('Cannot serve file from object ' . $ds->getMetaObject()->__toString() . ': object has no UID attribute!'));
            return new Response(404);
        }
        
        $attrContent = null;
        $attrMime = null;
        foreach ($ds->getMetaObject()->getAttributes() as $attr) {
            switch (true) {
                case $attr->getDataType() instanceof BinaryDataType:
                    $attrContent = $attr;
                    $ds->getColumns()->addFromAttribute($attr);
                    break;
                case $attr->getDataType() instanceof MimeTypeDataType:
                    $attrMime = $attr;
                    $ds->getColumns()->addFromAttribute($attr);
                    break;
            }
        }
        if ($attrContent === null) {
            $this->getWorkbench()->getLogger()->logException(new FacadeRuntimeError());
            return new Response(404);
        }
        
        $ds->getFilters()->addConditionFromAttribute($ds->getMetaObject()->getUidAttribute(), $uid, ComparatorDataType::EQUALS);
        $ds->dataRead();
        
        if ($ds->isEmpty()) {
            return new Response(404);
        }
        
        $binary = $attrContent->getDataType()->convertToBinary($ds->getColumns()->getByAttribute($attrContent)->getCellValue(0));
        
        // See if there are additional parameters 
        $params = [];
        parse_str($uri->getQuery() ?? '', $params);
        
        // Resize images
        if (null !== $resize = $params['resize'] ?? null) {
            list($width, $height) = explode('x', $resize);
            $binary = $this->resizeImage($binary, $width, $height);
        }
        
        // Create a response
        $headers = [];
        if ($attrMime !== null) {
            $headers['Content-Type'] = $ds->getColumns()->getByAttribute($attrMime)->getCellValue(0);
        }
        
        $response = new Response(200, $headers, stream_for($binary));
        return $response;
        
        return $handler->handle($request);
    }
    
    protected function resizeImage(string $src, int $width, int $height)
    {
        $img = (new ImageManager())->make($src);
        $img->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        return $img->encode();
    }
}