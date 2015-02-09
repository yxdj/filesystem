<?php
/**
 * @link https://github.com/yxdj
 * @copyright Copyright (c) 2014 xuyuan All rights reserved.
 * @author xuyuan <1184411413@qq.com>
 */

namespace yxdj\filesystem;

/*图像处理类
$img=new self(source);//获得原图名柄（原图路径）
$img->rgb(array(255,255,255));
$img->size($dwidth=0,$dheight=0,$sizeType=self::CUT);//默认：原宽，原高，切割
$img->save($destination);//包含保存格式
$img->screen($type);//显示格式
$img->delete();------------删除源图片
$img->close();-------------关闭源图句柄
实际类型和文件后缀是分开验证的，
保存的时候路经是什么类型就实际是什么类型了

处理过程：
1.创建一个所要大小的画板
2.在旧图中划出一个区域，在新图中也画出一个区域（不一定是画板大小）
3.把旧图的区域，复制到新图中去。

处理多次图片不方便，
还有异常处理，参数传入，
$img=new FlyImage();

$img->set(PUBLIC_PATH.'23_1334916707.jpg');
$img->save(200,200,[300,50,220,220],PUBLIC_PATH.'a1.png');


$img->set(PUBLIC_PATH.'23_1416293682.jpg');
$img->save(500,500,FlyImage::SET,PUBLIC_PATH.'b0.jpg');		
$img->save(200,200,FlyImage::CUT,PUBLIC_PATH.'b1.jpg');
$img->save(200,200,FlyImage::DEF,PUBLIC_PATH.'b2.jpg');
$img->save(1000,200,FlyImage::MAX,PUBLIC_PATH.'b3.png');
$img->save(200,200,[0,0,220,220],PUBLIC_PATH.'b4.png');
$img->save(200,200,[200,30,220,220],PUBLIC_PATH.'b5.png');

*/
class Image {
    const CUT=1;
    const DEF=2;
    const MAX=3;
	const SET=4;
	
	
	private $background=array(255,255,255);	//设置透明色，默认为黑色
    public  $status;       //是否成功
    public  $error;          //错误信息
	
	
    private $width_pro;         //改变前后宽度的比例
    private $height_pro;        //改变前后高度的比例
    private $img;               //原图的资源句柄
    private $new;               //新图的资源句柄
	private $tt;          		//原图片类型（真实）	
	private $st;          		//原图片类型（后缀）
	private $dt;          		//新图片类型（后缀）

	

    private $source;            //原图片地址
    private $destination;       //新图片地址
    private $type;              //原图片类型（实际，数字）裁剪方式

	
	
	
	//原图
    private $swidth;         //原图片宽度
    private $sheight;        //原图片高度	
    private $sx=0;       		//创建裁剪点X(默认为0不裁剪)     
    private $sy = 0;   			//创建裁剪点Y(默认为0不裁剪) 
    private $sw=200;     		//设定的裁剪W
    private $sh=200;    		//设定的裁剪H	    
	
	
	
	//新图,只需要宽高就可，其它的已能计算得来，再给的话图片将达不成效果
    private $dwidth;      		//容器宽度(输出，新图时的参数大小)
    private $dheight;        	//容器高度(输出，新图时的参大小)
    private $dx=0;       		//创建裁剪点X(默认为0不裁剪)     
    private $dy=0;    			//创建裁剪点Y(默认为0不裁剪) 
    private $dw;        	 	//新图片的宽度w(过渡，参数大小->扩大)
    private $dh;        		//新图片的高度h(过渡，参数大小->扩大)    
	
    //构造方法，初始化
    public function __construct($confs=array()){
		$this->init();
	}
	
	private function init(){
		$this->status=false;
		$this->error='init: cut image fail!';
	}




	//设定来源图片
	public function set($source){
		$this->init();
		
		//取得原图片地址->磁盘地址
		if(!is_file($this->source=$source)){
                $this->error='来源文件不存在';
                return false;			
		}

		//取得图片的宽，高，类型
		list($this->swidth, $this->sheight, $this->tt)=getimagesize($this->source);

	
		$type=$this->tt;
        switch ($type) {
            case 1 : $img = imagecreatefromgif($source);break;
            case 2 : $img = imagecreatefromjpeg($source);break;
            case 3 : $img = imagecreatefrompng($source);break;				
            default:$this->error='图片处理类不能获取此图片: '.$source;return false;
        }
		$this->img=$img;
        return true;
	}
	
  
    
      
    //向显示器输出图片
    public function screen($dwidth = 0,$dheight = 0,$sizeType=self::CUT,$dt=null){
        $this->init();
		$this->size($dwidth,$dheight,$sizeType);
		
		
		$info=pathinfo($this->source);//取得原后缀名
        $this->st=$info['extension'];    
        $dt==null?$this->dt=$this->st:$this->dt=$dt;
        switch ($this->dt) {
            case 'gif' :
                $img = imagegif($this->new);
                break;
            case 'jpg' :
                $img = imagejpeg($this->new);
                break;
            case 'jpeg' :
                $img = imagejpeg($this->new);
                break;				
            case 'png' :
                $img = imagepng($this->new);
                break;
            default:
                $this->error='图片处理类不能生成此格式图片: '.$this->dt;
                return false;
        }
        imagedestroy($this->new);//消毁新图资源句柄
		$this->status=true;
		return true;
    }
      
    //保存图片
    public function save($dwidth = 0,$dheight = 0,$sizeType=self::CUT,$destination=null){
        $this->init();
		$this->size($dwidth,$dheight,$sizeType);
		
		//不指定目标路径将覆盖原文件
		$destination==null?$this->destination=$this->source:$this->destination=$destination;
		$info=pathinfo($this->destination);
        $this->dt=$info['extension'];
		
		if(!is_dir($info['dirname'])){
			$this->status=false;
			$this->error='图片所移至的目标路径不存在！';
			return false;
		}
        switch ($this->dt) {
            case 'gif' :
                $img = imagegif($this->new,$this->destination);
                break;
            case 'jpg' :
                $img = imagejpeg($this->new,$this->destination);
                break;
            case 'jpeg' :
                $img = imagejpeg($this->new,$this->destination);
                break;				
            case 'png' :
                $img = imagepng($this->new,$this->destination);
                break;				
            default:
                $this->status=false;
                $this->error='图片处理类不能生成此格式图片: '.$this->dt;
                return false;
        } 
          
        imagedestroy($this->new);//消毁新图资源句柄，只用一次，每一次都不一样
		$this->status=true;
		return true;
    } 










    //改变图片尺寸
    private function size($dwidth = 0,$dheight = 0,$sizeType=self::CUT){
        //分析传递的参数，宽和高
        //宽高不是数字，宽高没设定，设定为0，---->原大小输出
        if (!is_numeric($dwidth) || !is_numeric($dheight)||$dwidth==0||$dheight==0) {
            $this->dwidth = $this->swidth;//新宽==原宽
            $this->dheight = $this->sheight;//新高==原高
        }else{  //其它情况则按传递的参数大小输出图片
            $this->dwidth = $dwidth;//新宽==传宽
            $this->dheight = $dheight;//新高==传高   
        }
		
		$this->dw=$this->dwidth;
		$this->dh=$this->dheight;
		
		if(is_array($sizeType)){
			$this->sx=$sizeType[0];
			$this->sy=$sizeType[1];
			$this->sw=$sizeType[2];
			$this->sh=$sizeType[3];
			$this->dx=0;
			$this->dy=0;
			$sizeType=self::SET;
		}else{
			$this->sx=0;
			$this->sy=0;
			$this->sw=$this->swidth;
			$this->sh=$this->sheight;			
		}
		
        switch($sizeType){
            case self::CUT: $this->cutSize();break;
            case self::DEF: $this->defSize();break;
            case self::MAX: $this->maxSize();break;
			case self::SET: $this->setSize();break;
			
            default:
                $this->error='图片处理类不能以此种方式处理图片: '.$sizeType;
                return false;
        }
		return true;
    } 



	
	
	//设置背景色
	public function background($rgb){
		$this->background=$rgb;
	}
	
	public function __set($name,$value){
		$this->$name=$value;
	}
	
	public function __get($name){
		return $this->$name;
	}



	
	
    //不压缩变形，达到给定大小，会裁剪
	/*
	这里采用的是放大的方式，
	还有一种缩小的方式，去原图中找点位
	*/
    public function cutSize2() {       
        //得到参数，即以后要生成的图片的内容的大小
        $this->dw = $this->dwidth;
        $this->dh = $this->dheight;
        $this->sw = $this->swidth;
        $this->sh = $this->sheight;   
  
        //求比例，重设新图宽高，设裁剪点，此新图宽高是指从旧图拿出来的大小，当然是不能比旧图小  
        $this->width_pro=$this->dwidth/$this->swidth;
        $this->height_pro=$this->dheight/$this->sheight;
        if($this->width_pro < $this->height_pro){
            $this->dwidth =$this->swidth*$this->height_pro;//高的比例大，就让宽*大比例
            $this->sx =-($this->dwidth - $this->dw)/2; //求出裁剪点的宽度  
            $this->sy=0;
        }else{
			$this->dheight =$this->sheight*$this->width_pro;//宽的比例大，就让高*大比例    
            $this->sy = -($this->dheight - $this->dh)/2; //求出裁剪点的高度  
            $this->sx=0;
        }
        //创建新图
        $this->new = imagecreatetruecolor($this->dw,$this->dh);
		
        //设置纯黑色为透明
        $transparent= imagecolorallocate($this->new,$this->background[0],$this->background[1],$this->background[2]);
        imagecolortransparent($this->new,$transparent);
        imagefilledrectangle($this->new,0,0,$this->dw,$this->dh,$transparent);
          
          
        //从旧图生成新图
        //参数：新句柄，旧句柄，新图裁剪点XY,旧图裁剪点XY,新图（拿出来的图）大小，旧图大小
        imagecopyresampled($this->new,$this->img,$this->sx,$this->sy,0,0,
                           $this->dwidth,$this->dheight,$this->sw,$this->sh);
    }
      
  
 
	//压缩处理，和其它其种的裁剪方式就要统一点了
    public function cutSize() {       
        //得到参数，即以后要生成的图片的内容的大小
        $this->dw = $this->dwidth;
        $this->dh = $this->dheight;
        $this->sw = $this->swidth;
        $this->sh = $this->sheight; 

  
        //求比例，缩小
        $this->width_pro=$this->swidth/$this->dwidth;
        $this->height_pro=$this->sheight/$this->dheight;
        if($this->width_pro > $this->height_pro){
            $this->sw =$this->dwidth*$this->height_pro;//宽的比例大，就让宽*小比例
            $this->sx =($this->swidth - $this->sw)/2; //求出裁剪点的宽度  
            $this->sy=0;
        }else{
			$this->sh =$this->dheight*$this->width_pro;//高的比例大，就让高*小比例    
            $this->sy =($this->sheight - $this->sh)/2; //求出裁剪点的高度  
            $this->sx=0;
        }
        //创建新图
        $this->new = imagecreatetruecolor($this->dwidth,$this->dheight);
		
        //设置纯黑色为透明
        $transparent= imagecolorallocate($this->new,$this->background[0],$this->background[1],$this->background[2]);
        imagecolortransparent($this->new,$transparent);
        imagefilledrectangle($this->new,0,0,$this->dwidth,$this->dheight,$transparent);
          
          
        //从旧图生成新图
        //参数：新句柄，旧句柄，新图裁剪点XY,旧图裁剪点XY,新图（拿出来的图）大小，旧图大小
        imagecopyresampled($this->new,$this->img,0,0,$this->sx,$this->sy,
                           $this->dw,$this->dh,$this->sw,$this->sh);
    }








 
  
    //压缩变形，达到给定大小，不裁剪
    public function defSize() {       
        //得到参数，即以后要生成的图片的内容的大小
        $this->dw = $this->dwidth;
        $this->dh = $this->dheight;
        $this->sw = $this->swidth;
        $this->sh = $this->sheight;
		
        //创建新图
        $this->new = imagecreatetruecolor($this->dwidth,$this->dheight);
          
        //设置纯黑色为透明
		$transparent= imagecolorallocate($this->new,$this->background[0],$this->background[1],$this->background[2]);
        imagecolortransparent($this->new,$transparent);
        imagefilledrectangle($this->new,0,0,$this->dwidth,$this->dheight,$transparent);
          
          
        //从旧图生成新图
        //参数：新句柄，旧句柄，新图裁剪点XY,旧图裁剪点XY,新图（拿出来的图）大小，旧图大小
        imagecopyresampled($this->new,$this->img,0,0,0,0,
                            $this->dw,$this->dh,$this->sw,$this->sh);
    }
      
  
  
    //不压缩变形，不裁剪，在给定大小内最大化,以全景呈现
    public function maxSize() {       
        //把传递的参数赋给容器，即以后要生成的图片大小
  
        //求比例，重设新图宽高，设裁剪点，此新图宽高是指从旧图拿出来的大小，当然是不能比旧图大  
        $this->width_pro=$this->dwidth/$this->swidth;
        $this->height_pro=$this->dheight/$this->sheight;
        if ($this->width_pro < $this->height_pro) {
            $this->dh =$this->sheight*$this->width_pro;//宽的比例小，就让高*小比例    
            //$this->sy = -($this->dheight - $this->dh)/2; //求出裁剪点的高度
        } else {
            $this->dw =$this->swidth*$this->height_pro;//高的比例小，就让宽*小比例
            //$this->sx =-($this->dwidth - $this->dw)/2; //求出裁剪点的宽度        
        }
          
        //创建新图,
        $this->new = imagecreatetruecolor($this->dwidth,$this->dheight);
          
        //设置纯黑色为透明
		$transparent= imagecolorallocate($this->new,$this->background[0],$this->background[1],$this->background[2]);
        imagecolortransparent($this->new,$transparent);
        imagefilledrectangle($this->new,0,0,$this->dwidth,$this->dheight,$transparent);
  
        //从旧图生成新图
        //参数：新句柄，旧句柄，新图裁剪点XY,旧图裁剪点XY,新图（拿出来的图,等会再放到wrap里）大小，旧图大小
        imagecopyresampled($this->new,$this->img,0,0,0,0,
                            $this->dw,$this->dh,$this->sw,$this->sh);
    }
 
 
 
 
 
//--------------------------------------------------
    //根据指定裁剪点，达到给定大小，裁剪，更贴合用户
    public function setSize() {
        //把传递的参数赋给容器，即以后要生成的图片大小	
		
        //创建新图
        $this->new = imagecreatetruecolor($this->dwidth,$this->dheight);
          
        //设置纯黑色为透明
        $transparent= imagecolorallocate($this->new,$this->background[0],$this->background[1],$this->background[2]);
        imagecolortransparent($this->new,$transparent);
        imagefilledrectangle($this->new,0,0,$this->dwidth,$this->dheight,$transparent);
          
        //从旧图生成新图
        //参数：新句柄，旧句柄，新图裁剪点XY,旧图裁剪点XY,新图（拿出来的图）大小，旧图大小
        imagecopyresampled($this->new,$this->img,0,0,$this->sx,$this->sy,
                            $this->dw,$this->dh,$this->sw,$this->sh);
    }
	
//-------------------------------------------


 
  
    public function close(){
        imagedestroy($this->img);//消毁旧图资源句柄
    }
    public function delete(){
        unlink($this->source);   
    }
}
  
?>