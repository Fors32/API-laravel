<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class RemovePostsWithoutImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        foreach (Post::all(['id', 'title']) as $post) {
            if ($post->getMedia('files')->isEmpty()) {
                $post->delete();
            }
        }
        return true;
    }
}
