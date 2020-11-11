<?php
namespace BlueRaster\CSFilesystem;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Psr\Log\LoggerInterface;
use AppBundle\Service\HttpHelper;
use AppBundle\Controller\ui\TokenAuthenticatedController;
use AppKernel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Carbon\Carbon;

class FilesController extends Controller implements TokenAuthenticatedController {


    private function getRootdir(){
        if($this->rootdir) return $this->rootdir;
//         $this->rootdir = Utils::storage_path(str_start($this->getUser()->getUsername(), '/'));
//         $this->rootdir = Utils::storage_path();
        $this->rootdir = $this->container->getParameter('csweb_api_files_folder');
        return $this->rootdir;
    }

    private function getFilesystem(){
        if($this->filesystem) return $this->filesystem;

        $this->rootdir = $this->getRootdir();
        $fileManager = new FileManagerFlysystem(['rootFolder' => $this->rootdir]);
        if($fileManager->adapter === 'local'){
	        if(! file_exists($this->rootdir . '/.gitignore') ){
		        file_put_contents($this->rootdir . '/.gitignore', "*\n!.gitignore\n");
	        }
        }
        $this->filesystem = $fileManager->getFilesystem();
        return $this->filesystem;
    }

    public $rootdir;

    public $filesystem;
    

    private function derive_path($path){
        $path = '/' . Utils::clean_path($path);

        return $this->getRootdir() . "$path/";
    }


    private function url($path = ''){
        $is_file = str_contains($path, '.');
        $url = '/file-manager/' . ltrim($path, '/');
        return !$is_file ? str_finish($url, '/') : $url;
    }


    /**
     * @Route("/file-manager/{filePath}", name="files", methods={"GET"}, requirements={"filePath"=".*?"})
     */
    public function viewFiles(Request $request, $filePath = ''){
        if($this->getFilesystem()->has($filePath) && $this->filesystem->getMimetype($filePath) != "directory"){
            $newfilename = $request->get('new_filename');
            if($newfilename){
                $newpath = dirname($filePath) . "/$newfilename";
                return new Response((string) $this->filesystem->rename($filePath, $newpath));
            }
            else{
                $callback = function() use($filePath){
                    $outputStream = fopen('php://output', 'wb');
                    $fileStream = $this->filesystem->readStream($filePath);
                    stream_copy_to_stream($fileStream, $outputStream);
                };

                return new StreamedResponse($callback, 200, [
                    'Content-Type' => $this->filesystem->getMimetype($filePath),
                    'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                ]);
            }
        }

        $files = [];
        if(!empty($filePath) && !$this->getFilesystem()->has($filePath)){
            throw new NotFoundHttpException('The requested folder does not exist');
        }
        $paths = collect($this->getFilesystem()->listContents($filePath));
/*
        $filtered_paths = $paths->filter(function($v) {
            return $this->can_view_path($this->getUser() , $v['path']);
        });
*/
		$filtered_paths = $paths;

        foreach ($filtered_paths as $fileInfo) {
            if($fileInfo['basename'][0] === '.') continue;
            $link = empty($filePath) ? $this->url($fileInfo['basename']) : $this->url("$filePath/") . $fileInfo['basename'] ;
            $files[] = [
	            'name' => $fileInfo['basename'], 
	            'is_dir' => $fileInfo['type'] == "dir", 
	            'link' => $link,
	            'timestamp' => (new Carbon($fileInfo['timestamp']))->toDateTimeString(),
            ];
        }

//         dd($files);

        $data = [
        	'filePath' => $filePath, 
        	'foldername' => basename($filePath), 
        	'files' => $files, 
        	'parent_dir' => $this->url(dirname($filePath)),
	        'access_token' => $request->cookies->get('access_token'),	
    	];

        return $this->render('@CSFilesystem/files.twig', $data);

    }


    /**
     * @Route("/file-manager/{filePath}", name="createFolder", methods={"PUT"}, requirements={"filePath"=".*?"})
     */
    public function createFolder(Request $request, $filePath = ''){
        $newfolder = $request->get('foldername');
        $rename = $request->get('rename') == true;
        if(empty($newfolder)){
            return new Response('false');
        }
        if($rename) {
            $dirname = dirname($filePath) == '.' ? '' : dirname($filePath) . '/';	        
            $renamed = "$dirname/$newfolder";
            
            $this->getFilesystem()->rename($filePath, $renamed);
            return new Response($this->url($renamed));
        }
        else {
            $success = $this->getFilesystem()->createDir("$filePath/" . ltrim($newfolder, '/'));

            return new Response((string) $success);
        }
    }


    /**
     * @Route("/file-manager/{filePath}", name="deleteFolder", methods={"DELETE"}, requirements={"filePath"=".*?"})
     */
    public function deleteFolder(Request $request, $filePath = ''){
        $filePath = Utils::clean_path($filePath);
        if(empty($filePath)) {
	        throw new NotFoundHttpException('You cannot delete a protected folder.');
            return false;
            die();
        }

        // check to see if this is the only permitted folder
        $diff = array_diff([$filePath], @$this->getUser()->permitted_paths ?? []);
        if(empty($diff)) {
            throw new NotFoundHttpException('You cannot delete a protected folder.');
            return;
        }
        $stringpath = implode('_', explode('/', $filePath));
        $renamed = "/.trash/__trashedon__" . time() . "__$stringpath";
        $this->getFilesystem()->rename($filePath, $renamed);
        $dirname = dirname($filePath) == '.' ? '' : dirname($filePath) . '/';
        return new Response($this->url($dirname));
    }


    /**
     * @Route("/file-manager/{filePath}", name="uploadFiles", methods={"POST"}, requirements={"filePath"=".*?"})
     */

    public function uploadFiles(Request $request, $filePath = ''){
	    
        $create_path = $this->derive_path($filePath);
        $files = $request->files->get('uploads');
        if(!is_array($files)) $files = [$files];
        $filecount = count($files);
        $successes = 0;
        foreach($files as $file){
            if($file->isValid()){
                $filename = $file->getClientOriginalName();
                $file->move($create_path, $filename);
                if(file_exists($create_path . $filename)) $successes++;
            }
        }
        if($filecount == $successes){
		    return new RedirectResponse("/file-manager/$filePath");
        }
        return new Response("An error occurred");

    }

}
