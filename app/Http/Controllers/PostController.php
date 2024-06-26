<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Follower;
use App\Models\Friend;
use App\Models\Post;
use App\Models\PostLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public function create(Request $request)
    {
        $client = $request->user('');

        if ($request->hasFile('images') && count($request->images) > 0) {
            $images = $request->file('images');
            $fileNames = [];
            foreach ($images as $image) {
                if ($image->isValid()) {
                    $file_name = $image->getClientOriginalName();
                    $image->move(public_path('img/post'), time() . "_" . $file_name);
                    $fileNames[] = 'post/' . time() . "_" . $file_name;
                }
            }
            $result = json_encode($fileNames, JSON_THROW_ON_ERROR);
            $arr['images'] = $result; // Thêm key 'images' với giá trị từ $result
        } else {
            $arr = $request->all(); // Loại bỏ key 'images' nếu nó tồn tại trong request
        }
        $arr['caption'] = $request->caption;
        $arr['privacy'] = $request->privacy;
        $arr['id_client'] = $client->id;
        $post = Post::create($arr);
        if ($post) {
            return response()->json([
                'status'    => 1,
                'post'      => $post,
                'message'   => 'Posted successfully!',
            ]);
        } else {
            return response()->json([
                'status'    => 0,
                'message'   => 'Posting error!',
            ]);
        }
    }
    public function dataPost(Request $request)
    {
        $client = $request->user();
        $id_client = $client->id;
        $friends = Friend::where('my_id', $client->id)
            ->select('id_friend as result_id')
            ->union(
                Friend::where('id_friend', $client->id)
                    ->select("my_id as result_id")
            )
            ->pluck('result_id');
        $followers = Follower::where('my_id', $client->id)
            ->select('id_follower')->pluck('id_follower');

        $post = Post::join('clients', 'clients.id', '=', 'posts.id_client')
            ->select('posts.*', 'clients.username', 'clients.fullname', 'clients.avatar')
            ->where(function ($query) use ($friends, $id_client) {
                $query->where('posts.privacy', Post::friend)
                    ->whereIn('posts.id_client', $friends)
                    ->orWhere('posts.id_client', $id_client);
            })
            ->orWhere(function ($query) use ($id_client, $friends, $followers) {
                $query->where('posts.privacy', Post::public);
            })
            ->orWhere(function ($query) use ($id_client) {
                $query->where('posts.privacy', Post::private)
                    ->where('posts.id_client', $id_client);
            })
            ->orderByDESC('posts.created_at')
            ->get();
        foreach ($post as $key => $value) {
            $check = PostLike::where('id_post', $value->id)->where('id_client', $client->id)->first();
            $totalLikes = PostLike::where('id_post', $value->id)->count();
            if ($check) {
                $post[$key]['liked'] = 1;
            } else {
                $post[$key]['liked'] = 0;
            }
            $post[$key]['likes'] = $totalLikes;
            $comments = Comment::where('id_post', $value->id)->get();
            $post[$key]['comments'] = count($comments);
        }
        return response()->json([
            'status' => 1,
            'dataPost'    => $post,
            'message'    => 'oke',
        ]);
    }
    public function dataProfile(Request $request)
    {
        $client = $request->user();
        $id_client = $client->id;
        $friends = Friend::where('my_id', $client->id)
            ->select('id_friend as result_id')
            ->union(
                Friend::where('id_friend', $client->id)
                    ->select("my_id as result_id")
            )
            ->pluck('result_id');
        $followers = Follower::where('my_id', $client->id)
            ->select('id_follower')->pluck('id_follower');

        $post = Post::join('clients', 'clients.id', '=', 'posts.id_client')
            ->select('posts.*', 'clients.username', 'clients.fullname', 'clients.avatar')
            ->where(function ($query) use ($friends, $id_client, $followers) {
                $query->where(function ($query) use ($friends, $id_client) {
                    $query->where('posts.privacy', Post::friend)
                        ->whereIn('posts.id_client', $friends)
                        ->orWhere('posts.id_client', $id_client);
                })
                    ->orWhere(function ($query) use ($id_client, $friends, $followers) {
                        $query->where('posts.privacy', Post::public);
                    })
                    ->orWhere(function ($query) use ($id_client) {
                        $query->where('posts.privacy', Post::private)
                            ->where('posts.id_client', $id_client);
                    });
            })
            ->where('username', $request->username)
            ->orderByDESC('posts.created_at')
            ->get();
        foreach ($post as $key => $value) {
            $check = PostLike::where('id_post', $value->id)->where('id_client', $client->id)->first();
            $totalLikes = PostLike::where('id_post', $value->id)->count();
            if ($check) {
                $post[$key]['liked'] = 1;
            } else {
                $post[$key]['liked'] = 0;
            }
            $post[$key]['likes'] = $totalLikes;
            $comments = Comment::where('id_post', $value->id)->get();
            $post[$key]['comments'] = count($comments);
        }
        $poster = Client::where('username', $request->username)->first();
        $quatity = Post::where('id_client', $poster->id)->count();
        return response()->json([
            'status' => 6,
            'dataPost'    => $post,
            'quatity'    => $quatity,
        ]);
    }
    public function destroy(Request $request)
    {
        try {
            DB::beginTransaction();
            $post = Post::find($request->id);
            if ($post) {
                $comments = Comment::where('id_post', $post->id)->get();
                $post_likes = PostLike::where('id_post', $post->id)->get();
                foreach ($post_likes as $key => $value) {
                    $value->delete();
                }
                foreach ($comments as $key => $value) {
                    $likes = CommentLike::where('id_comment', $value->id)->get();
                    foreach ($likes as $k => $v) {
                        $v->delete();
                    }
                    $value->delete();
                }
                $post->delete();
                DB::commit();
                return response()->json([
                    'status' => 1,
                    'message' => 'deleted successfully!',
                ]);
            } else {
                return response()->json([
                    'status' => -1,
                    'message' => 'This post was not found!',
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 0,
                'message' => 'delete failed!',
            ]);
        }
    }
    public function update(Request $request)
    {
        try {
            DB::beginTransaction();
            $post = Post::find($request->id);
            if ($post) {
                $arr = $request->all();
                $post->update($arr);
                DB::commit();
                return response()->json([
                    'status'    => 1,
                    'post'      => $post,
                    'message'   => 'Updated successfully!',
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'    => 0,
                'message'   => 'Failed update',
            ]);
        }
    }

    public function updatePrivacy(Request $request)
    {
        try {
            DB::beginTransaction();

            $post = Post::find($request->id);
            $post->update([
                'privacy' => $request->privacy
            ]);
            DB::commit();
            return response()->json([
                'status'    => 1,
            ]);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return response()->json([
                'status'    => 0,
                'message'   => '',
            ]);
        }
    }
    public function dataDetailPost($id_post, Request $request)
    {
        $likes = PostLike::where('id_post', $id_post)->get();
        $post = Post::where('posts.id', $id_post)
            ->join('clients', 'posts.id_client', 'clients.id')
            ->select('posts.*', 'clients.username', 'clients.fullname', 'clients.avatar')->first();
        $comments = Comment::where('id_post', $id_post)
            ->join('clients', 'comments.id_client', 'clients.id')
            ->select('comments.*', 'clients.username', 'clients.fullname', 'clients.avatar')->get();
        foreach ($comments as $key => $value) {
            $replies = Comment::where('id_replier', $value->id)
                ->join('clients', 'comments.id_client', 'clients.id')
                ->select('comments.*', 'clients.username', 'clients.fullname', 'clients.avatar')
                ->get()
                ->toArray();
            foreach ($replies as &$reply) {
                $liked = CommentLike::where('id_comment', $reply['id'])
                    ->where('id_client', $request->user()->id)
                    ->exists();
                $reply['liked'] = $liked;
            }

            $comments[$key]['replies'] = $replies;
            $comments[$key]->liked = CommentLike::where('id_comment', $value->id)
                ->where('id_client', $request->user()->id)
                ->exists();
        }
        $post['comments'] = $comments;
        $hasliked = $likes->contains('id_client', $request->user()->id);
        $post['likes'] = $likes;
        $post['hasLiked'] = $hasliked;
        return response()->json([
            'detailPost'   => $post,
        ]);
    }
}
