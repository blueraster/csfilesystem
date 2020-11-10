# CSFilesystem

## Install

```
php composer.phar require blueraster/csfilesystem

## or use below if you receive an error about minimum-stability

php composer.phar require blueraster/csfilesystem dev-main
```
> If you receive errors on installation see the [Troubleshooting Installation](#troubleshooting-installation) section at the bottom of this document

## Configuration

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

> If you're getting an error message on the `/file-manager/` page, you need to clear the application's cache. Delete files in the directory `var/cache`



## JS Snippet to add menu item 

If you would like a link to the files page, add the following snippet to the bottom of the JavaScript file located at: `dist/js/sb-admin-2.js`

```js
(function(d){
	('addEventListener' in window) && window.addEventListener("load", function(){
		var menu = d.getElementById("side-menu");
		var li = d.createElement("li");
		li.innerHTML = '<a href="/file-manager"><i class="fa fa-folder-o fa-fw"></i> List Files</a>';
		menu && menu.appendChild(li);			
	});
})(document);
```

## Troubleshooting Installation

In some cases you may receive errors during installation. Please note the following for help with this.

#### PHP Version Errors
You may receiver an error on installation mentioning PHP versions. For some versions of CSWeb, a specific PHP version has been specified in your `package.json` file.
To remove it from the command line: 

```
php composer.phar config --unset platform.php
```

Or simply remove the section manually from `composer.json`

#### Out of Memory Error

If you receive an error similar to:
 
```
PHP Fatal error:  Allowed memory size of 1610612736 bytes exhausted (tried to allocate 134217736 bytes)
```

This is due to a script intended for internal development that runs on updates.
To fix this, add `--no-scripts` to all `require` and `install` commands.
To permanently prevent this, use the command: 

```
php composer.phar config --unset scripts.post-update-cmd
```
