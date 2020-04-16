<?php

namespace App\Http\Controllers;

use App\Job;
use App\Jobs\ExampleJob;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class BatchTaskController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Retrieve the user for the given ID.
     *
     * @param  int  $id
     * @return Response
     */
    public function create(Request $request)
    {
        // dd($request->all());
        //Helper validator :D, should return a json response
        $tasks = $this->validate($request, [
            'tasks' => ['required', 'array'],
            'tasks.*.submitter_id' => 'required',
            'tasks.*.command' => 'required',
            'tasks.*.priority' => 'numeric'
        ]);

        $taskIds = [];
        $tasksData = $tasks['tasks'];
        foreach($tasksData as $task) {
            $command = $task['command'];
            $submitterId = $task['submitter_id'];
            $priority = $task['priority'];

            $job = new Job();
            $job->submitter_id = $submitterId;
            $job->command = $command;
            $job->save();
            $taskId = $job->id;

            //Again let's go in a Redis Transaction
            Redis::MULTI();
            Redis::zadd('priority_queue_list', $priority, $taskId);
            Redis::hset("priority_queue:{$taskId}", "command", $command);
            Redis::hset("priority_queue:{$taskId}", "status", false);
            Redis::hset("priority_queue:{$taskId}", "inqueue", true);
            Redis::EXEC();

            //Add to array
            $taskIds[] = $taskId;
        }

        return response()->json(
            [
                'taskIds' => $taskIds,
            ],
            201 // Successfully Created, YAY Restful
        );
    }
}
