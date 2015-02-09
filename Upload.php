<?php
/**
 * @link https://github.com/yxdj
 * @copyright Copyright (c) 2014 xuyuan All rights reserved.
 * @author xuyuan <1184411413@qq.com>
 */

namespace yxdj\filesystem;


//上传文件类

/*
这里仅处理对单个文件值的上传处理，
多个文件上传，对值的确定验证和格式化，在外部处理好之后再调用
因为这些是不确定的，把它放在接口处更加灵活

$file=new FlyUpload();
$file->upload($file,$root,$cut,$rname,$rsize,$rtypes);
if($file->status){
	$result['status']='ok';
	$result['url']=$cutpre.$file->main;
	$result['infox']=$file->disk;
}else{
	$result['status']='ng';
	$result['info']=$file->error;
}
$out=json_encode($result);
echo $out;





//如果是多文件上传，指单请求多文件
/*
经表单直接提交测试，其选多文件时即单请求多文件
命名为file,后面的覆盖前面的，
命名为file[],看到的现象是php并不会将单个文件的数据存放一块，而是文件名全在一块，类型全在一块，，
可能的接收方式：file['filename'][],file['size'][],或许这和多选框的上传是一致的
不是想象中的:file['name']['ffilename',...]

所以需要自已在这格式化成相应的文件数据再上传，
不断是哪种格式，如果多文件，但我只想要一个呢，如果将由类文件处理，很难满足需求变更，参数更复杂
在接口处处理，更灵活，上传类也更清淅
*/



//如果是大文件分段上传，指单文件多请求
/*
需要客端一个标识，可以是服务端上传的文件名及路径，可加密处理
这个标识仅靠sessionID,虽然可在session里存放标识，
但客户端每次的请求都是独立的，它要断开上传，重新上传，，这个在session里没法响应
可以在客户端设置一个cookie，标识一个特定的文件要上传，这样服务端能根据cookie做出判断

在这个过程中客户端是主动的且是多状态的，服务端是被动的。所以采用上述方式较好。
*/



/*
$file=new FlyUpload();
$filedata=$_FILES['file'];
//root,cut可以自行组织，root是参考路径不会动到，cut会截断处理
//返回的后台总是正常的，cut不是从根目录开始的则要自行剪接
//前台的路径用于在前台显示
//后台的路径，可能也需传向前台，之后回传后台接着处理，如点位处理
//把后台全传过去可能会公开结构了，可以只传后一段，前面的到后台再接上，
//这样只传完整的前台路径即可，前台跨域的话，要加上域名,
//这里的路径只要保证每一段被/或\分隔就行，内部会自行处理
//用SERVER变量可以自动侦测路径，写死也是可以的,但移动目录就会出错
//标准写法
//$root=$_SERVER['DOCUMENT_ROOT'];
//$cut=dirname($_SERVER['SCRIPT_NAME']).'/upload/'.date('Ymd').'/';

//安全写法,让更少的路径在cut中，这是要处理的部分;
//cutpre是省略的部分，后续要加在返回的前台路径上
$root=$_SERVER['DOCUMENT_ROOT'].'/'.dirname($_SERVER['SCRIPT_NAME']).'/upload/';
$cut=date('Ymd').'/';
$cutpre=preg_replace('#[\\\/]+#','/','/'.dirname($_SERVER['SCRIPT_NAME']).'/upload');

//文件名，提供后缀则以此后缀为文件名，否则就用上传文件的后缀（一般也就是这样），
//真实的类弄不读取文件内容是没办法知道的，浏览器也仅仅根据后缀判断类型而已，
$filename=date('YmdHis').mt_rand(1,999999);

//服务端能接收的文件最大byte限制,如果php配置层有更严格的限制，这个值没用
//客服务端的大小限制没用，需要的话仅在前端做更严格的限制即可，
//PHP可能会接收客服端的大小做判断，给出的结果处理过程也接收，但完全没必要在PHP代码里做处理
$size_s=3400000;

//可接收的上传文件类开型，不要点，仅根据后缀判断而已
$type=array('jpg','gif','png','zip','rar','doc','xls','ppt');

//(文件值,根路径,主目录,文件名,,最大值,允许当传的文件类型)
$file->upload($filedata,$root,$cut,$filename,$size_s,$type);

//根据上传结束后生成的状态生成返回结果
if($file->status){
	$result['status']='ok';
	$result['url']=$cutpre.$file->main;
	$result['infox']=$file->disk;
}else{
	$result['status']='ng';
	$result['info']=$file->error;
}

//将结果JSON化并输出
$out=json_encode($result);
echo $out;
*/

class Upload {
	
	private $fsize;				//上传文件实际大小	
	private $ferror;			//错误代码	
	private $ftype;				//上传文件类型
	private $fname;				//上传文件名
	private $ftmp;				//上传临时文件名
	
	private $rsize;    	 		//服务器程序最大值server(有时服务器配置也无权修改)		
	private $rname;				//文件名
	private $rtypes;			//类型合集	
	private $rootpath;			//根目录
	private $cutpath;			//中间目录	

	
	public  $main;			//含文件名，返回前台./filename
	public  $disk;			//含文件名，后台G:/filename	
	public 	$status;			//处理状态
	public 	$error;				//错误信息

	
	//构造方法，初始化
	public function upload($file,$root,$cut,$rname,$rsize,$rtypes){
		//初始化状态
		$this->init();
		
		//来源参数
		$this->fsize=$file['size'];
		$this->ferror = $file['error'];	
		$this->fname = $file['name'];
		$this->ftmp = $file['tmp_name'];
		$this->ftype = trim(strrchr($this->fname,'.'),'.');
		
		//可接收的参数
		$this->rsize = $rsize;
		$this->rtypes=$rtypes;		
		$this->rname=strpos($rname,'.')?$rname:$rname.'.'.$this->ftype;	
			
		//传进来的路径保证每一段至少有一了/或\就行
		//这里对root,cut做严格的处理，对传进来的参数过滤，方便文件生成，及对外输出
		$this->rootpath=preg_replace('#[\\\/]+#','/',$root.'/');		
		$this->cutpath=ltrim(preg_replace('#[\\\/]+#','/',$cut.'/'),'/');	
				
		//返回给前后台用的路径，如果main不是script_name,那返回后，前台自行剪接
		$this->main='/'.$this->cutpath.$this->rname;
		$this->disk=$this->rootpath.$this->cutpath.$this->rname;
		
		
		if(!$this->checkError()){return false;}
		if(!$this->checkType()){return false;}
		if(!$this->createUploadPath()){return false;}
		if(!$this->moveUpload()){return false;}
		$this->status=true;
		$this->error='upload success!';
		return true;
	}


	
	//初始化状态，去除上次残留消息
	private function init(){
		$this->status=false;
		$this->error='init: upload fail!';
	}


	
	//验证错误
	private function checkError(){
		$error=$this->ferror;
		if($this->fsize>$this->rsize ){$error=5;}
		if (!empty($error)){
			switch ($error){
				case 1 :$this->error='上传值超过了约定最大值！';break;
				case 2 :$this->error='文件过大-b！';break;
				case 3 :$this->error='只有部分文件被上传！';break;
				case 4 :$this->error='没有任何文件被上传！';break;
				case 5 :$this->error='文件过大-s！';break;
				default:$this->error='未知错误！';					
			}
			return false;
		}
		return true;
	}

	//验证类型
	private function checkType(){
		if (!in_array($this->ftype,$this->rtypes)) {
			$this->error='不合法的上传类型！'.$this->ftype;
			return false;			
		}
		return true;
	}	
	
	//创建上传文件目录
	private function createUploadPath(){
		if(!$this->createDir($this->rootpath,$this->cutpath)){
			$this->error='目录创建失败！';
			return false;			
		}
		return true;
	}


	//目录创建方法
	//只传一个参数时即为cut,root为空
	private function createDir($root,$cut=''){
			if($cut){
				$cut=trim($cut,'/').'/';
				$root=rtrim($root,'/').'/';
			}else{
				$cut=rtrim($root,'/').'/';	
				$root='';
			}

			
			//提取路径节点
			for($i=0;$i=strpos($cut,'/',++$i);){$dirs[]=substr($cut,0,$i);}
			//创建目录
			for($i=0;$i<count($dirs);$i++){
				if (!is_dir($root.$dirs[$i]) || !is_writable($root.$dirs[$i])) {
					if (!mkdir($root.$dirs[$i])) {//当目录不存在，或其不可写时创建一个
						return false;
					}
				}
			}
			return true;
	}			
		

	
	//移动文件
	private function moveUpload(){
		if (is_uploaded_file($this->ftmp)) {
			if (!move_uploaded_file($this->ftmp,$this->disk)){
				$this->error='上传失败！';
				return false;	
			}else{
				return true;
			}
		} else {
			$this->error='临时文件不存在！';
			return false;			
		}
	}

}