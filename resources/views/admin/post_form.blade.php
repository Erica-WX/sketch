@extends('layouts.default')
@section('title', '回帖管理')

@section('content')
<div class="container-fluid">
    <div class="col-sm-10 col-sm-offset-1 col-md-8 col-md-offset-2">
        @include('shared.errors')
        <div class="panel panel-default">
            <div class="panel-heading">
                <span class="admin-symbol">
                    管理帖子(请不要进行私人操作)
                </span>
                <h4><a href="{{route('post.show',$post->id)}}">{{ $post->title }}{{$post->brief}}</a></h4>
            </div>
            <div class="panel-body">
                <form action="{{ route('admin.postmanagement',$post->id)}}" method="POST">
                    {{ csrf_field() }}
                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="10" onclick="document.getElementById('majiaforpost{{$post->id}}').style.display = 'block'">修改马甲？</label>
                        <div class="form-group text-right" id="majiaforpost{{$post->id}}" style="display:none">
                            <label><input type="radio" name="is_anonymous" value="1" {{ $post->is_anonymous ? 'checked':'' }}>披上马甲</label>
                            <label><input type="radio" name="is_anonymous" value="2" {{ $post->is_anonymous ? '':'checked' }}>揭下马甲</label>
                            <input type="text" name="majia" class="form-control" value="{{$post->majia ?:'匿名咸鱼'}}">
                        </div>
                    </div>

                    @if($post->is_bianyuan)
                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="38">回帖转非边缘</label>
                    </div>
                    @else
                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="37">回帖转边缘</label>
                    </div>
                    @endif

                    @if($post->fold_state===0)
                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="11">折叠帖子</label>
                    </div>
                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="7">删除帖子</label>
                    </div>

                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="32">回帖折+禁（回帖折叠，发帖人禁言+一天）</label>
                        <h6 class="grayout">比如无意义争执车轱辘、在版务区不看首楼跟帖，在作者问题楼/他人讨论楼里问等级签到问题等情况</h6>
                    </div>

                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="32">回帖折+禁+清（回帖折叠，发帖人禁言+1天，积分等级清零）</label>
                        <h6 class="grayout">一直一直车轱辘、多次在版务区不看首楼跟帖，多次在作者问题楼/他人讨论楼里问等级签到问题等情况</h6>
                    </div>

                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="34">回帖折+清+封（回帖删除，等级清零，发言人禁止登陆1天）</label>
                        <h6 class="grayout">特别屡教不改、置管理于不顾的水区违禁</h6>
                    </div>

                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="35">回帖删+清+封（回帖删除，等级清零，发言人禁止登陆7天）</label>
                        <h6 class="grayout">辱骂作者，人身攻击</h6>
                    </div>

                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="36">回帖删+封（回帖折叠，等级清零，发言人永久禁止登陆））</label>
                        <h6 class="grayout">全部都是脏话粗话特别不堪入目的人身攻击</h6>
                    </div>
                    @else
                    <div class="radio">
                        <label><input type="radio" name="controlpost" value="12">取消折叠</label>
                    </div>
                    @endif

                    <div class="form-group">
                        <label for="reason"></label>
                        <textarea name="reason"  rows="3" class="form-control" placeholder="请输入处理理由，方便查看管理记录，如“涉及举报，标题简介违规”，“涉及举报，不友善”，“边限标记不合规”。"></textarea>
                    </div>
                    <div class="">
                        <button type="submit" class="btn btn-danger sosad-button btn-md admin-button">确定管理</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@stop