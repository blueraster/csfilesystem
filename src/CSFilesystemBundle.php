<?php

namespace BlueRaster\CSFilesystem;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CSFilesystemBundle extends Bundle
{
	public function __construct(){
		
// 		d($this->getContainerExtension());
/*
		$js = '<script>
	(function(d){
		window.addEventListener("load", function(){
			var menu = d.getElementById("side-menu");
			var li = d.createElement("li");
			li.innerHTML = \'<a href="/files" class="active"><i class="fa fa-folder-o fa-fw"></i> List Files</a>\';
			menu.appendChild(li);			
		});
	})(document);
</script>';
		echo $js;
*/
	}
}

