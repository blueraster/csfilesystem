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

class FilesController extends Controller implements TokenAuthenticatedController {

    /**
     *  // @ // Route("/files/{filePath}", name="files", methods={"GET"}, requirements={"filePath"=".*?"})
     */
/*
    public function viewFiles(Request $request, $filePath = null) {
        $client = $this->get(HttpHelper::class);
        //set the oauth token
        $access_token = $request->cookies->has('access_token') ? $request->cookies->get('access_token') : "";
        $authHeader = 'Bearer ' . $access_token;

        $response = $client->request('GET', 'files/' . $filePath, null, ['Authorization' => $authHeader, 'Accept' => 'application/json']);

        //unauthorized or expired  redirect to logout page
        if ($response->getStatusCode() == 401) {
            return $this->redirectToRoute('logout');
        }

        $files = json_decode($response->getBody());

        return $this->render('@CSFilesystem/files.twig', [
	        'files' => $files,
	        'filePath' => $filePath,
	        'parent_dir' => dirname($filePath),
	        'access_token' => $access_token,
	        'foldername'  => $filePath,

        ]);
    }
*/

    private function getRootdir(){
        if($this->rootdir) return $this->rootdir;
        $this->rootdir = Utils:: storage_path(str_start(@$this->getUser()->filepath, '/'));
        return $this->rootdir;
    }

    private function getFilesystem(){
        if($this->filesystem) return $this->filesystem;

        $this->rootdir = $this->getRootdir();
        $fileManager = new FileManagerFlysystem(['rootFolder' => $this->rootdir]);
        $this->filesystem = $fileManager->getFilesystem();
        return $this->filesystem;
    }

    public $rootdir;
    public $filesystem;

    protected $middleware = [
        'authenticated_middleware',
        'protected_folder_middleware',
    ];



    private function derive_path($path){
        $path = '/' . Utils::clean_path($path);

        return $this->getRootdir() . "$path/";
    }


    private function url($path = ''){
        $is_file = str_contains($path, '.');
        $url = '/files/' . ltrim($path, '/');
        return !$is_file ? str_finish($url, '/') : $url;
    }




    public function connect(Application $app){
        $controllers = $app['controllers_factory'];
        $controllers->get('/', 'Controllers\UploadController::viewFiles');
        $controllers->get('/{path}', 'Controllers\UploadController::viewFiles')->assert("path", ".*")->bind('view_files');
        $controllers->put('/{path}', 'Controllers\UploadController::createFolder')->assert("path", ".*");
        $controllers->post('/{path}', 'Controllers\UploadController::uploadFiles')->assert("path", ".*");
        $controllers->delete('/{path}', 'Controllers\UploadController::deleteFolder')->assert("path", ".*");

        $controllers->before(function(Request $request){
            foreach($this->middleware as $fn){
                $guarded = $this->{$fn}($request, $app);
                if($guarded) return $guarded;
            }
        });
        return $controllers;
    }


/*
    private function authenticated_middleware(Request $request){
        $accesss_token = "";
        $app['current_user'] = $this->getUser($app, $app['request']->cookies->get('username'), $app['request']->cookies->get('access_token'));

        if( $app['current_user'] === false ){
        // if( !$app['request']->cookies->has('access_token')){
            return $app->redirect($app["url_generator"]->generate('login'));
        }
        else{
            $this->rootdir = Utils::base_path('/files') . str_start($app['current_user']['filepath'], '/');
            $fileManager = new FileManagerFlysystem(['rootFolder' => $this->rootdir]);
            $this->filesystem = $fileManager->getFilesystem();
//             dd($this);
        }
    }
*/

/*
    private function protected_folder_middleware(Request $request, Application $app){
        $path = $request->get('path');
        if(empty($path)) {
            return false;
        }
        if($this->can_view_path($this->getUser(), $path) === false) {
            throw new NotFoundHttpException('The requested folder does not exist');
        }
    }
*/


    private function can_view_path($user, $path){

        $path = rtrim(trim($path), '/');
        $path = explode('/', $path);
        if(count($path) === 1) return true;

        foreach($path as $segment){
            if(starts_with($segment, '.')) return false;
        }
        if(@$user->is_super_admin == 1) return true;

        return true;


        return in_array(head($path), @$user->permitted_paths);
    }


/*
    protected function getUser()

		parent::getUser();

	}
*/

/*
    public function getUser(Application $app, $username, $access_token)
    {
        $client = $app['services.httphelper'];
        $response = $client->request('GET', 'users/'.$username, null, ['Content-Type' => 'application/json',
            'Authorization'=> 'Bearer ' . $access_token,
            'Accept' => 'application/json']);

        $response = json_decode($response->getBody(),true);
        return isset($response['username']) ? $response : false;
    }
*/


    /**
     * @Route("/files/{filePath}", name="files", methods={"GET"}, requirements={"filePath"=".*?"})
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
        $filtered_paths = $paths->filter(function($v) {
            return $this->can_view_path($this->getUser() , $v['path']);
        });

        foreach ($filtered_paths as $fileInfo) {
            if($fileInfo['basename'][0] === '.') continue;
            $link = empty($filePath) ? $this->url($fileInfo['basename']) : $this->url("$filePath/") . $fileInfo['basename'] ;
            $files[] = ['name' => $fileInfo['basename'], 'is_dir' => $fileInfo['type'] == "dir", 'link' => $link];
        }

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
     * @Route("/files/{filePath}", name="createFolder", methods={"PUT"}, requirements={"filePath"=".*?"})
     */
    public function createFolder(Request $request, $filePath = ''){
        $newfolder = $request->get('foldername');
        $rename = $request->get('rename') == true;
        if(empty($newfolder)){
            return new Response('false');
        }
        if($rename) {
            $renamed = dirname($filePath) . "/$newfolder";
            $this->getFilesystem()->rename($filePath, $renamed);
            $dirname = dirname($filePath) == '.' ? '' : dirname($filePath) . '/';
            return new Response($this->url($renamed));
        }
        else {
            $success = $this->getFilesystem()->createDir("$filePath/" . ltrim($newfolder, '/'));

            return new Response((string) $success);
        }
    }


    /**
     * @Route("/files/{filePath}", name="deleteFolder", methods={"DELETE"}, requirements={"filePath"=".*?"})
     */
    public function deleteFolder(Request $request, $filePath = ''){
        $filePath = Utils::clean_path($filePath);
        if(empty($filePath)) {
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
     * @Route("/files/{filePath}", name="uploadFiles", methods={"POST"}, requirements={"filePath"=".*?"})
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
		    return new RedirectResponse("/files/$filePath");
        }
        return new Response("An error occurred");

    }

}
