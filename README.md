# CSFilesystem

### Install

```
composer require blueraster/csfilesystem
```
> You may receiver an error on installation mentioning PHP versions. For some versions of CSWeb, a specific PHP version has been specified in you `package.json` file.
> To remove it from the command line: `composer config --unset platform.php`

### Configuration

Register the bundle in `app/AppKernel.php`

```php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            
            // add this line before AppBundle()
            new BlueRaster\CSFilesystem\CSFilesystemBundle(),
            
            new AppBundle\AppBundle(),            
        ];

```

Add the routes to `app/config/routing.yml`

```yaml
app:
    resource: '@AppBundle/Controller/ui'
    type: annotation
csfilesystem:
    resource: '@CSFilesystemBundle/FilesController.php'
    type: annotation    
```

> If you're getting an error message on the `/files/` page, you need to clear the application's cache. Delete files in the directory `var/cache`


### JS Snippet to add menu item 

If you would like a link to the files page, add the following snippet to the bottom of the JavaScript file located at: `dist/js/sb-admin-2.js`

```js
(function(d){
	('addEventListener' in window) && window.addEventListener("load", function(){
		var menu = d.getElementById("side-menu");
		var li = d.createElement("li");
		li.innerHTML = '<a href="/files" class="Xactive"><i class="fa fa-folder-o fa-fw"></i> List Files</a>';
		menu && menu.appendChild(li);			
	});
})(document);
```
