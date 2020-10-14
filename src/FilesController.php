<?php
namespace BlueRaster\CSFilesystem;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Psr\Log\LoggerInterface;
use AppBundle\Service\HttpHelper;
use AppBundle\Controller\ui\TokenAuthenticatedController;

class FilesController extends Controller implements TokenAuthenticatedController {

    /**
     * @Route("/files/{filePath}", name="files", methods={"GET"}, requirements={"filePath"=".*?"})
     */
    public function viewFileListSubfolderAction(Request $request, $filePath = null) {
        $client = $this->get(HttpHelper::class);
        //set the oauth token
        $access_token = $request->cookies->has('access_token') ? $request->cookies->get('access_token') : "";
        $authHeader = 'Bearer ' . $access_token;

        $response = $client->request('GET', 'files/' . $filePath, null, ['Authorization' => $authHeader, 'Accept' => 'application/json']);
// dd($response->getBody()); die();
        //unauthorized or expired  redirect to logout page
        if ($response->getStatusCode() == 401) {
            return $this->redirectToRoute('logout');
        }

        $files = json_decode($response->getBody());

        return $this->render('files.twig', [
	        'files' => $files,
	        'filePath' => $filePath,
	        'parent_dir' => dirname($filePath),
	        'access_token' => $access_token,
	        'foldername'  => $filePath,
	        
        ]);
    }
    
    private function getRootdir($app){
        if($this->rootdir) return $this->rootdir;

        $this->rootdir = base_path('/files') . str_start($app['current_user']['filepath'], '/');
        return $this->rootdir;
    }

    private function getFilesystem($app){
        if($this->filesystem) return $this->filesystem;

        $this->rootdir = $this->getRootdir($app);
        $fileManager = new FileManagerFlysystem(['rootFolder' => $this->rootdir]);
        $this->filesystem = $fileManager->getFilesystem();
        return $this->filesystem;
    }
    

/*
    public $rootdir;
    public $filesystem;

    protected $middleware = [
        'authenticated_middleware',
        'protected_folder_middleware',
    ];



    private function derive_path($app, $path){
        $path = '/' . clean_path($path);

        return $this->getRootdir($app) . "$path/";
    }


    private function url($path = ''){
        $is_file = str_contains($path, '.');
        $url = '/ui/upload/' . ltrim($path, '/');
        return !$is_file ? str_finish($url, '/') : $url;
    }


    public function connect(Application $app){
        $controllers = $app['controllers_factory'];
        $controllers->get('/', 'Controllers\UploadController::viewFiles');
        $controllers->get('/{path}', 'Controllers\UploadController::viewFiles')->assert("path", ".*")->bind('view_files');
        $controllers->put('/{path}', 'Controllers\UploadController::createFolder')->assert("path", ".*");
        $controllers->post('/{path}', 'Controllers\UploadController::uploadFiles')->assert("path", ".*");
        $controllers->delete('/{path}', 'Controllers\UploadController::deleteFolder')->assert("path", ".*");

        $controllers->before(function(Request $request, Application $app){
            foreach($this->middleware as $fn){
                $guarded = $this->{$fn}($request, $app);
                if($guarded) return $guarded;
            }
        });
        return $controllers;
    }


    private function authenticated_middleware(Request $request, Application $app){
        $accesss_token = "";
        $app['current_user'] = $this->getUser($app, $app['request']->cookies->get('username'), $app['request']->cookies->get('access_token'));

        if( $app['current_user'] === false ){
        // if( !$app['request']->cookies->has('access_token')){
            return $app->redirect($app["url_generator"]->generate('login'));
        }
        else{
            $this->rootdir = base_path('/files') . str_start($app['current_user']['filepath'], '/');
            $fileManager = new FileManagerFlysystem(['rootFolder' => $this->rootdir]);
            $this->filesystem = $fileManager->getFilesystem();
//             dd($this);
        }
    }

    private function protected_folder_middleware(Request $request, Application $app){
        $path = $request->get('path');
        if(empty($path)) {
            return false;
        }
        if($this->can_view_path($app['current_user'], $path) === false) {
            throw new NotFoundHttpException('The requested folder does not exist');
        }
    }


    private function can_view_path($user, $path){

        $path = rtrim(trim($path), '/');
        $path = explode('/', $path);
        if(count($path) === 1) return true;

        foreach($path as $segment){
            if(starts_with($segment, '.')) return false;
        }
        if($user['is_super_admin'] == 1) return true;

        return true;


        return in_array(head($path), $user['permitted_paths']);
    }


    public function getUser(Application $app, $username, $access_token)
    {
        $client = $app['services.httphelper'];
        $response = $client->request('GET', 'users/'.$username, null, ['Content-Type' => 'application/json',
            'Authorization'=> 'Bearer ' . $access_token,
            'Accept' => 'application/json']);

        $response = json_decode($response->getBody(),true);
        return isset($response['username']) ? $response : false;
    }


    public function viewFiles(Application $app, $path = ''){
        if($this->getFilesystem($app)->has($path) && $this->filesystem->getMimetype($path) != "directory"){
            $newfilename = $app['request']->get('new_filename');
            if($newfilename){
                $newpath = dirname($path) . "/$newfilename";
                return new Response((string) $this->filesystem->rename($path, $newpath));
            }
            else{
                $callback = function() use($path){
                    $outputStream = fopen('php://output', 'wb');
                    $fileStream = $this->filesystem->readStream($path);
                    stream_copy_to_stream($fileStream, $outputStream);
                };
                return new StreamedResponse($callback, 200, [
                    'Content-Type' => $this->filesystem->getMimetype($path),
                    'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                ]);
            }
        }

        $files = [];
        if(!empty($path) && !$this->getFilesystem($app)->has($path)){
            throw new NotFoundHttpException('The requested folder does not exist');
        }

        $paths = collect($this->getFilesystem($app)->listContents($path));
        $filtered_paths = $paths->filter(function($v) use($app){
            return $this->can_view_path($app['current_user'] , $v['path']);
        });

        foreach ($filtered_paths as $fileInfo) {
            if($fileInfo['basename'][0] === '.') continue;
            $link = empty($path) ? $this->url($fileInfo['basename']) : $this->url("$path/") . $fileInfo['basename'] ;
            $files[] = ['name' => $fileInfo['basename'], 'is_dir' => $fileInfo['type'] == "dir", 'link' => $link];
        }

        $data = ['path' => $path, 'foldername' => basename($path), 'files' => $files, 'parent_dir' => $this->url(dirname($path))];

        return $app['twig']->render('upload.twig', $data);
    }



    public function createFolder(Application $app, $path = ''){
        $newfolder = $app['request']->get('foldername');
        $rename = $app['request']->get('rename') == true;
        if(empty($newfolder)){
            return new Response('false');
        }
        if($rename) {
            $renamed = dirname($path) . "/$newfolder";
            $this->getFilesystem($app)->rename($path, $renamed);
            $dirname = dirname($path) == '.' ? '' : dirname($path) . '/';
            return new Response($this->url($renamed));
        }
        else {
            $success = $this->getFilesystem($app)->createDir("$path/" . ltrim($newfolder, '/'));

            return new Response((string) $success);
        }
    }



    public function deleteFolder(Application $app, $path = ''){

        $path = clean_path($path);
        if(empty($path)) {
            return false;
            die();
        }

        // check to see if this is the only permitted folder
        $diff = array_diff([$path], $app['current_user']['permitted_paths']);
        if(empty($diff)) {
            throw new NotFoundHttpException('You cannot delete a protected folder.');
            return;
        }
        $stringpath = implode('_', explode('/', $path));
        $renamed = "/.trash/__trashedon__" . time() . "__$stringpath";
        $this->getFilesystem($app)->rename($path, $renamed);
        $dirname = dirname($path) == '.' ? '' : dirname($path) . '/';
        return new Response($this->url($dirname));
    }



    public function uploadFiles(Application $app, $path = ''){
        $create_path = $this->derive_path($app, $path);
        $files = $app['request']->files->get('uploads');
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
        if($filecount == $successes) return $app->redirect($app["url_generator"]->generate('view_files', ['path' => $path]));
        return new Response("An error occurred");

    }
*/

}
