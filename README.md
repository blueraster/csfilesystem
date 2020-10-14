# CSFilesystem

### Install

```
composer require blueraster/csfilesystem
```


### Configuration

Register the bundle in `app/AppKernel.php`

```
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
