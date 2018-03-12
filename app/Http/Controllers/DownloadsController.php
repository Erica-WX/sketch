<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;
use App\Label;
use App\Thread;
use App\Book;
use App\Post;
use App\Chapter;
use App\Tag;
use Carbon\Carbon;
use App\Tongren;
use App\Download;
use Auth;

class DownloadsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function __construct()
    {
      $this->middleware('auth')->except(['index']);
    }
    public function index()
    {
        //
    }
    public function print_book_info($thread)
    {
      $book_info = Config::get('constants.book_info');
      $book = $thread->book;
      $txt = "标题：".$thread->title."\n";
      $txt .= "简介：".$thread->brief."\n";
      $txt .= "作者：";
      if($thread->anonymous){$txt.=($thread->majia ?? "匿名咸鱼");}else{$txt.=$thread->creator->name;}
      $txt .= " at ".Carbon::parse($thread->created_at)->setTimezone(8);
      if($thread->created_at < $thread->edited_at){
        $txt.= "/".Carbon::parse($thread->edited_at)->setTimezone(8);
      }
      $txt .= "\n";
      $txt .= "图书信息：".$book_info['originality_info'][$book->original].'-'.$book_info['book_lenth_info'][$book->book_length].'-'.$book_info['book_status_info'][$book->book_status].'-'.$book_info['sexual_orientation_info'][$book->sexual_orientation];
      if($thread->bianyuan){$txt .= "|边缘";}
      $txt .= '|'.$thread->label->labelname;
      foreach ($thread->tags as $tag){
        $txt .= '-'.$tag->tagname;
      }
      $txt .="\n文案：\n".$this->process_text($thread->body,$thread->mainpost->markdown,$thread->mainpost->indentation)."\n";
      return $txt;
    }

    public function print_thread_info($thread)
    {
      $txt = "标题：".$thread->title."\n";
      $txt .= "简介：".$thread->brief."\n";
      $txt .= "发帖人：";
      if($thread->anonymous){$txt.=($thread->majia ?? "匿名咸鱼");}else{$txt.=$thread->creator->name;}
      $txt .= " at ".Carbon::parse($thread->created_at)->setTimezone(8);
      if($thread->created_at < $thread->edited_at){
        $txt.= "/".Carbon::parse($thread->edited_at)->setTimezone(8);
      }
      $txt .="\n正文：\n".$this->process_text($thread->body,$thread->mainpost->markdown,$thread->mainpost->indentation);
      return $txt;
    }
    public function reply_to_sth($post)
    {
      $txt = "";
      if($post->reply_to_post_id!=0){
        $txt .= "回复".($post->reply_to_post->anonymous ? ($post->reply_to_post->majia ?? '匿名咸鱼') : $post->reply_to_post->owner->name).$post->reply_to_post->trim($post->reply_to_post->title . $post->reply_to_post->body, 20)."\n";
      }elseif(($post->chapter_id!=0)&&(!$post->maintext)&&($post->chapter->mainpost->id>0)){
        $txt .= "评论".$post->trim( $post->chapter->title . $post->chapter->mainpost->title . $post->chapter->mainpost->body , 20)."\n";
      }
      return $txt;
    }
    public function process_text($string,$markdown,$indentation)
    {
      if($markdown){
        $string = Helper::sosadMarkdown($string);
      }else{
        $string = Helper::wrapParagraphs($string);
      }
      if($indentation)
      {
        $string = str_replace("<p>", "<p>　　", $string);
      }
      $string = Helper::htmltotext($string);
      return $string;
    }
    public function generate_thread_text(Thread $thread)
    {
      $posts = Post::where([
        ['thread_id', '=', $thread->id],
        ['id', '<>', $thread->post_id]
        ])
        ->with(['owner','reply_to_post.owner','chapter','comments.owner'])
        ->oldest()
        ->get();
      $thread->load(['channel','creator', 'tags', 'label', 'mainpost.comments.owner']);
      $txt = 'Downloaded from http://sosad.fun by '.Auth::user()->name.' '.Auth::user()->id.' at UTC+8 '.Carbon::now(8)."\n";
      if (($thread->book_id>0)&&(Auth::user()->id!=$thread->user_id)){
        $txt .= "仅供个人备份使用，请勿私自传播，所有权利属于原作者。For personal backup only. All rights reserved to the author.\n";
      }
      if($thread->book_id>0){
         $txt .=$this->print_book_info($thread);
          }else{
         $txt .=$this->print_thread_info($thread);
      }
      $postcomments = $thread->mainpost->comments;
      foreach($postcomments as $k => $postcomment){
        $txt .= "主楼点评".($k+1).": ";
        if($postcomment->anonymous){$txt.=($postcomment->majia ?? "匿名咸鱼");}else{$txt.=$postcomment->owner->name;}
        $txt .= ' '.Carbon::parse($postcomment->created_at)->setTimezone(8)."\n";
        $txt .= $postcomment->body."\n";
      }
      $txt .= "\n";
      foreach($posts as $i=>$post){
        $txt.="回帖".($i+1).": ";

        if($post->maintext){
          if($thread->anonymous){$txt.=($thread->majia ?? "匿名咸鱼");}else{$txt.=$thread->creator->name;}
        }else{
          if($post->anonymous){$txt.=($post->majia ?? "匿名咸鱼");}else{$txt.=$post->owner->name;}
        }
        $txt .= " ".Carbon::parse($post->created_at)->setTimezone(8);
        if($post->created_at < $post->edited_at){
          $txt .= "/".Carbon::parse($post->edited_at)->setTimezone(8);
        }
        $txt .= "\n";
        $txt .= $this->reply_to_sth($post);
        if($post->maintext){$txt .= $post->chapter->title."\n";}
        if($post->title){$txt .= $post->title."\n";}
        $txt .= $this->process_text($post->body,$post->markdown,$post->indentation);
        if($post->chapter->annotation){$txt .= "备注".$this->process_text($post->chapter->annotation,1,0);}

        foreach($post->comments as $k => $postcomment){
          $txt .= "回帖".($i+1)."点评".($k+1).": ";
          if($postcomment->anonymous){$txt.=($postcomment->majia ?? "匿名咸鱼");}else{$txt.=$postcomment->owner->name;}
          $txt .= " ".Carbon::parse($postcomment->created_at)->setTimezone(8)."\n";
          $txt .= $postcomment->body."\n";
        }
        $txt .= "\n";
      }
      $txt .= 'Downloaded from http://sosad.fun by '.Auth::user()->name.' '.Auth::user()->id.' at UTC+8 '.Carbon::now(8)."\n";
      if (($thread->book_id>0)&&(Auth::user()->id!=$thread->user_id)){
        $txt .= "仅供个人备份使用，请勿私自传播，所有权利属于原作者。For personal backup only. All rights reserved to the author.\n";
      }
      return $txt;
     }
      public function generate_book_noreview_text(Thread $thread)
      {
        $book = $thread->book;
        $chapters = $book->chapters;
        $chapters->load(['mainpost']);
        $thread->load(['creator', 'tags', 'label']);
        $book_info = Config::get('constants.book_info');
        $txt = 'Downloaded from http://sosad.fun by Username:'.Auth::user()->name.' UserID:'.Auth::user()->id.' at UTC+8 '.Carbon::now(8)."\n";
        if (($thread->book_id>0)&&(Auth::user()->id!=$thread->user_id)){
          $txt .= "仅供个人备份使用，请勿私自传播，所有权利属于原作者。For personal backup only. All rights reserved to the author.\n";
        }
        $txt .=$this->print_book_info($thread);
        foreach($chapters as $i=>$chapter){
          $txt .= ($i+1).'.'.$chapter->title."\n";//章节名
          $txt .= Carbon::parse($chapter->created_at)->setTimezone(8);
          if($chapter->created_at < $chapter->edited_at){
            $txt.= "/".Carbon::parse($chapter->edited_at)->setTimezone(8);
          }
          $txt .= "\n";
          if($chapter->mainpost->title){$txt .= $chapter->mainpost->title."\n";}
          if($chapter->mainpost->body){$txt .= $this->process_text($chapter->mainpost->body,$chapter->mainpost->markdown,$chapter->mainpost->indentation)."\n";}
          if($chapter->annotation){$txt .= "备注：".$this->process_text($chapter->mainpost->annotation,1,0);}
          $txt .="\n";
        }
        $txt .= 'Downloaded from http://sosad.fun by '.Auth::user()->name.' '.Auth::user()->id.' at UTC+8 '.Carbon::now(8)."\n";
        if (($thread->book_id>0)&&(Auth::user()->id!=$thread->user_id)){
          $txt .= "仅供个人备份使用，请勿私自传播，所有权利属于原作者。For personal backup only. All rights reserved to the author.\n";
        }return $txt;
      }
     public function thread_txt(Thread $thread)
     {
        $user = Auth::user();
        if (($user->id!=$thread->user_id)||(!$user->admin)) {//假如并非本人主题，登陆用户也不是管理员，首先看主人是否允许开放下载
          if (!$thread->download_as_thread){
            return redirect()->back()->with("danger","作者并未开放下载");
          }else{
            if($user->user_level>0){
              if ($thread->book_id > 0){//图书的下载需要更多剩饭咸鱼
                if (($user->user_level>=2)&&($user->shengfan > 10)&&($user->xianyu > 2)){
                  $user->decrement('shengfan',10);
                  $user->decrement('xianyu',2);
                }else{
                  return redirect()->back()->with("danger","您的等级或剩饭与咸鱼不够，不能下载");
                }
              }else{//下载讨论帖需要的剩饭稍微少一些
                if ($user->shengfan > 5){
                  $user->decrement('shengfan',5);
                }else{
                  return redirect()->back()->with("danger","您的剩饭与咸鱼不够，不能下载");
                }
              }
            }else{
              return redirect()->back()->with("danger","您的用户等级不够，不能下载");
            }
          }
        }

        if($thread->user_id!=$user->id){//并非作者本人下载，奖励部分
          $author = $thread->creator;
          $author->increment('shengfan',5);
          $author->increment('jifen',5);
          $author->increment('xianyu',1);
          $thread->increment('downloaded');
        }
        if ($thread->book_id>0){$format = 1;}else{$format = 0;}
        $download = Download::create([
          'user_id' => $user->id,
          'thread_id' => $thread->id,
          'format' => $format,
        ]);
        $txt = $this->generate_thread_text($thread);//制作所需要的文档

        $response = new StreamedResponse();
        $response->setCallBack(function () use($txt) {
            echo $txt;
        });
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'thread'.$thread->id.'.txt');
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
     }
     public function book_noreview_text(Thread $thread)
     {
        $user = Auth::user();
        if (($user->id!=$thread->user_id)&&(!$user->admin)){//假如并非本人主题，登陆用户也不是管理员，首先看主人是否允许开放下载
          if (!$thread->download_as_book){
            return redirect()->back()->with("danger","作者并未开放下载");
          }else{
            if($user->user_level>4){
              if (($user->shengfan > 10)&&($user->xianyu > 2)){
                $user->decrement('shengfan',10);
                $user->decrement('xianyu',2);
              }else{
                return redirect()->back()->with("danger","您的剩饭与咸鱼不够，不能下载");
              }
            }else{
              return redirect()->back()->with("danger","您的用户等级不够，不能下载");
            }
          }
        }
        if($thread->user_id!=$user->id){//并非作者本人下载，奖励部分
          $author = $thread->creator;
          $author->increment('shengfan',10);
          $author->increment('jifen',10);
          $author->increment('xianyu',2);
          $thread->increment('downloaded');
        }
        $download = Download::create([
          'user_id' => $user->id,
          'thread_id' => $thread->id,
          'format' => 3,
        ]);

        $txt = $this->generate_book_noreview_text($thread);//制作所需要的下载文档
        $response = new StreamedResponse();
        $response->setCallBack(function () use($txt) {
            echo $txt;
        });
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'book'.$thread->book_id.'.txt');
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
      }
}
