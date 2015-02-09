yxdj/filesystem for php
=====================================

实际类型和文件后缀是分开验证的，
保存的时候路经是什么类型就实际是什么类型了

处理过程：
1.创建一个所要大小的画板
2.在旧图中划出一个区域，在新图中也画出一个区域（不一定是画板大小）
3.把旧图的区域，复制到新图中去。

处理多次图片不方便，
还有异常处理，参数传入，
```php
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
```



