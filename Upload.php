<?php
/**
 * @link https://github.com/yxdj
 * @copyright Copyright (c) 2014 xuyuan All rights reserved.
 * @author xuyuan <1184411413@qq.com>
 */

namespace yxdj\filesystem;


/**
 * 必需传入两个路径：
 * 后缀不确定，所以事先没法确定磁盘路径，url路径
 * 磁盘路径和url路径的前导，都要传入，这就致使引用处得对两路径分别生成
 *
 * 为什么不传入两完整路径:
 * 两路径的开始位置大多确定，但结束部分要分部写出，中间没停顿少了信息，很多时候有公共部分,
 * 会做多余处理
 *
 * 传入两个可拼接的路径，
 * root往往可以非常简便的得到，1.$_SERVER['DOCUMENT_ROOT'],2.__DIR__
 * 所以统一只确定cut即可，1.当前目录，$_SERVER['SCRIPT_NAME'],2.其它的手工指定
 * 这样使操作类知道了中间点信息，更方便处理
 * 注：
 * 需要url的前导cutpre，促使cutpre+cut+filename,能被web访问,原因=》
 * 1.由于root的指定可能进入了web目录，cut需要补充
 * 2.有内部路径，外部路径之别，可能需要加入协议和域名
 *
 * 坏处：
 * 做成通用类就会需要一些必要的信息，在某些时候，这些必要的信息相对直接写就有些多余，
 * 参数的生成显得做作。如果类确实满足不了需求，操作显得多余，那就直接写，或在此基础上专门改写。
 *
 * 好处：
 * 不用每次做上传功能都去完成很多繁琐的操作
 * 而一些参数的获取会显示得固定而有规律，
 * 虽然有时显得多余，但因有规律，代码会清淅，开发会快速，排错会简单
 */
class Upload 
{
	
    //错误代码	
	private $ferror;

    //上传临时文件名
	private $ftmp;	
    
    //上传文件实际大小	
    private $fsize;

    //上传文件类型  
	private $ftype;

    //上传文件名
    private $fname;				
	
    //服务器程序最大值server(有时服务器配置也无权修改)	
	private $rsize;

    //类型合集
	private $rtypes;
    
    //文件名
	private $rname;				

    //需要创建的目录
    private $path;              
    
    //含文件名，返回前台./filename
	public  $main;	

    //含文件名，后台G:/filename
	public  $disk;			    
	
    //处理状态
	public 	$status;
    
    //错误信息
	public 	$error;	
    
    


	/**
     * 入口方法，执行上传
     */
	public function upload($file,$root,$cut,$rname,$rsize=0,$rtypes=array())
    {

		//初始化状态，所有参数再次执行都将重写
		$this->status=false;
		$this->error='init: upload fail!';
		
		//来源文件参数
		$this->ferror = $file['error'];	
		$this->fname = $file['name'];
		$this->ftmp = $file['tmp_name'];
        $this->fsize=$file['size'];
		$this->ftype = trim(strrchr($this->fname,'.'),'.');
		
		//可接收的文件参数
        //当指定文件后缀时，使用指定的，没有指定时使用源文件后缀
        //大多时候自定义文件名，获取源后缀
		$this->rsize = $rsize;
		$this->rtypes=$rtypes;
        
        //获取文件后缀,参数优先于原文件后缀
        //参数中只要有点就用参数
		$this->rname=strpos($rname,'.')?$rname:$rname.'.'.$this->ftype;
        
        //将边缘的点去除
        $this->rname=trim($this->rname, '.');
        
        //如果文件名为空，报错返回
        if ($this->rname == '') {
            $this->status=true;
            $this->error='filename is empty';
            return false;        
        }
			

        //返回给前后台用的路径，如果main不是script_name,那返回后，前台自行剪接 
        $this->path = rtrim(preg_replace('#[\\\/]+#','/',$root.'/'.$cut), '/');
        $this->main = preg_replace('#[\\\/]+#','/','/'.$cut.'/'.$this->rname);
        $this->disk = preg_replace('#[\\\/]+#','/',$root.'/'.$cut.'/'.$this->rname);
				
		
        //执行上传操作
		if(!$this->checkError()){return false;}
		if(!$this->createUploadPath()){return false;}
		if(!$this->moveUpload()){return false;}
		$this->status=true;
		$this->error='upload success!';
		return true;
	}



	/**
     * 验证错误
     */
	private function checkError()
    {
		$error=$this->ferror;
		if (!empty($error)){
			switch ($error){
				case 1 :
                    $this->error = 'big file(server set)';
                    break;
				case 2 :
                    $this->error = 'big file(brower set)';
                    break;
				case 3 :
                    $this->error = 'only a part of file be upload';
                    break;
				case 4 :
                    $this->error = 'no file upload';
                    break;
                case 6:
                    $this->error = 'not font stm dir';
                    break;
                case 7:
                    $this->error = 'hdd write fail';
                    break;                
				default:
                    $this->error='unknow error';				
			}
			return false;
		}
        
        
        //为0表示不验证
		if ($this->rsize != 0 && $this->fsize > $this->rsize ){
            $this->error='big file(php set)';
            return false;
        }

        //验证类型:为空表示允许所有

		if (!empty($this->rtypes) && !in_array($this->ftype,$this->rtypes)) {
			$this->error='file extension fail('.$this->ftype.')';
			return false;			
		}   

		return true;
	}

	/**
     * 创建上传文件目录
     */
	private function createUploadPath()
    {
		if(!$this->createDirectory($this->path)){
			$this->error='create uploadpath fail';
			return false;			
		}
		return true;
	}

    /**
     * 目录创建方法
     */
    private function createDirectory($path, $mode = 0775, $recursive = true)
    {
        if (is_dir($path)) {
            return true;
        }
        $parentDir = dirname($path);
        if ($recursive && !is_dir($parentDir)) {
            $this->createDirectory($parentDir, $mode, true);
        }
        $result = mkdir($path, $mode);
        chmod($path, $mode);

        return $result;
    }      
        
        
        
	/**
     * 移动文件
     */
	private function moveUpload()
    {
		if (is_uploaded_file($this->ftmp)) {
			if (!move_uploaded_file($this->ftmp,$this->disk)){
				$this->error='move uploaded file fail';
				return false;	
			}else{
				return true;
			}
		} else {
			$this->error='tmp file not font';
			return false;			
		}
	}

}




/*
//上传文件类

/*
//如果要上传的文件不存在，自然是处理失败
if(!isset($_FILES['file'])){
	$result['status']='ng';
	$result['info']='update file ng,may too big!';
	echo json_encode($result);
	exit;
}

//文件处理类实例
$file=new FlyUpload();

//文件信息
$filedata=$_FILES['file'];

//root路径，前面是磁盘根目录，后面是cut路径
$root=$_SERVER['DOCUMENT_ROOT'];

//cut路径,前面是root,后面是filename
$cut=dirname($_SERVER['SCRIPT_NAME']).'/upload/'.date('Ymd');

//url的前导，促使cutpre+cut+filename,能被web访问
//cutpre,上传类本身不使用，在接口片生成有效url需要它
//url的前导cutpre，促使cutpre+cut+filename,能被web访问,原因=》
//1.由于root的指定可能进入了web目录，cut需要补充
//2.有内部路径，外部路径之别，可能需要加入协议和域名
$cutpre='http://www.doamin.com';

//文件名，提供后缀则以此后缀为文件名，否则就用上传文件的后缀（一般也就是这样），
//真实的类型不读取文件内容是没办法知道的，浏览器也仅仅根据后缀判断类型而已，
$filename=date('YmdHis').mt_rand(1,999999);


//可接收的上传文件类型，不要点，仅根据后缀判断而已
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





关于文件上传处理的相关说明

仅用于简单，快速的文件上传处理，如有需要，自行改写。
这里面集成了一些操作细节，做为一个快速使用的文件上传类
因而不可能满足所有的需求，如有其它需要，相关执行过程完全可以自行编写

验证：
1，根据服务器设定参数，及上传过程中php抛出的错误解析
2，大小验证
3，文件后缀验证

路径拼接，路径创建，文件移动，返回结果



必要的参数：
1.file参数：获取错误信息，大小，文件名
2.root,cut,filename
传来的路径，开关加/,结束不加/,有些时候不是必需，但按这个规则来最好
为什么要将3者分离？各处都要用的，就不要重复生成，抽离出来共用即可

如果有现成的，集成了要生成的部分，可以直接使用，
但另外一条没有集成，它还是需要生成，

就算两条都集成了，但类是为大多数不集成的考虑，所以也要传递统一的参数适应接口
除非不使用类，自已另写，那怎么处理都是好的了。



这里仅处理对单个文件值的上传处理，
多个文件上传，对值的确定验证和格式化，在外部处理好之后再调用
因为这些是不确定的，把它放在接口处更加灵活







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
