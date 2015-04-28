<?php
/**
 * @link https://github.com/yxdj
 * @copyright Copyright (c) 2014 xuyuan All rights reserved.
 * @author xuyuan <1184411413@qq.com>
 */

namespace yxdj\filesystem;

/**
 * 文件上传类，
 * 相关说明：
 * 文件上传，必定要确定两个路径：内部磁盘路径，外部可访问路径
 * 为了促使快捷使用，内置的一些功能需要相关参数，如需要将路径分段传入，
 * 如此格式固定而有规律，代码会清淅，开发会快速，排错会简单 
 * 如果有时显得多余和不便，那另行实现便是。
 * 
 * 多文件上传
 * 单请求一域多文件：
 * php接收后并不会将文件单独隔离，而是错误和错误在一起，大小和大小在一起，
 * 如此，那便需要调整格式之后再处理
 * 
 * 单文件多请求
 * 需要客端一个标识，可以是服务端上传的文件名及路径，可加密处理
 * 客户端是主动的且是多状态的，服务端是被动的。
 * 客户端每次请求独立，要断开上传，重新上传，在session里没法即时响应
 * 可以在客户端设置一个cookie，标识一个特定的文件要上传，这样服务端能根据cookie做出判断
 * 
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
     * @param $file array,指定文件域的数组信息
     * @param $root string，一般指磁盘根目录到web根目录的路经
     *        1.$_SERVER['DOCUMENT_ROOT'],2.__DIR__
     * @param $cut string,一般指web根目录到文件名的路径
     *        1.当前目录，$_SERVER['SCRIPT_NAME'],2.其它的手工指定
     * @param $rname string，文件名，如果有'.'将不另外查找后缀，没点将使用源文件后缀
     * @param $rsize ini,限定上传文件大小，单位byte,默认为0(即不限定大小)
     * @param $rtypes array,限定上传文件后缀扩展，不含'.',
     *
     * 注意：
     * 除传入的必要3段路径外，可能不需cutpre，促使cutpre+cut+filename,能被web访问,原因=》
     * 1.由于root的指定可能进入了web目录，cut需要补充
     * 2.有同域路径，跨域路径之别，可能需要加入协议和域名
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
