<?php

namespace App\Http\Controllers;

use App\Job;
use App\Jobs\ExampleJob;
use App\Jobs\Job as JobsJob;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TaskController extends Controller
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
    public function show($taskId)
    {
        $status = Redis::hget("priority_queue:{$taskId}", "status");
        return response()->json(["status" => $status ? "task has been processed successfully" : "task is pending"]);
    }

    public function index()
    {
        $success = false;
        try {
            //Lets find the task with the highest priority through redis
            $task = Redis::zrevrange('priority_queue_list', 0, 0);
            $taskId = $task[0];
            //Enter Redis Transaction
            Redis::MULTI();
            Redis::zrem('priority_queue_list', $taskId);
            Redis::hset("priority_queue:{$taskId}", "inqueue", false);
            Redis::EXEC();
            $success = true;
        } catch (\Exception $e) {
            $success = false;
        }
        if ($success) {
            $command = Redis::hget("priority_queue:{$taskId}", "command");
            //jobid and command are sent as a response
            return response()->json(
                [
                    'taskId' => (int) $taskId,
                    'command' => $command
                ]
            );
        } else {
            return response()->json(
                [
                    'error' => "Something went wrong!"
                ],
                400
            );
        }
    }

    /**
     * Retrieve the user for the given ID.
     *
     * @param  int  $id
     * @return Response
     */
    public function create(Request $request)
    {
        //Helper validator :D, should return a json response
        $this->validate($request, [
            'submitter_id' => 'required',
            'command' => 'required',
            'priority' => 'numeric'
        ]);

        $priority = $request->input("priority", 1); //Let's add a default value automatically if it doesn't exit
        $command = $request->input("command");
        $task = Job::create([
            "command" => $command,
            "submitter_id" => $request->input("submitter_id")
        ]);

        $taskId = $task->id;

        //Enter Redis Transaction, I've never used this so this is super cool!
        Redis::MULTI();
        Redis::zadd('priority_queue_list', $priority, $taskId);
        Redis::hset("priority_queue:{$taskId}", "command", $command);
        Redis::hset("priority_queue:{$taskId}", "status", false);
        Redis::hset("priority_queue:{$taskId}", "inqueue", true);
        Redis::EXEC();

        return response()->json(
            [
                'taskId' => $taskId,
            ],
            201 // Successfully Created, YAY Restful
        );
    }

    public function update(Request $request, $taskId) {

        //Validate payload
        $this->validate($request, [
            'processor_id' => 'numeric'
        ]);

        $processor_id = $request->input('processory_id');
        $success = false;
        $task = Job::find($taskId);

        if (!$task) {
            return response()->json(['error' => "Task {$taskId} does not exist"]);
        }

        //Let's check if the task is in the queue
        $taskInQueue = Redis::hget("priority_queue:{$taskId}", "inqueue");

        if ($taskInQueue) {
            return response()->json(["response" => "Task is in queue, unable to update"]);
        }

        $task->processor_id = $processor_id;
        
        //Lets start a database transaction to make sure everything stays in sync
        DB::beginTransaction();
        try {
            $task->save();
            DB::commit();
            $message = "successfully";
        } catch (Exception $e) {
            DB::rollBack();
            $message = "unsuccessfully";
        }

        if ($message === "successfully") {
            //IF all went well, lets update the task status in the queue
            Redis::hset("priority_queue:{$taskId}", "status", true);
        }

        return response()->json([
            "response" => "Task has been processed {$message}"
        ]);
    }
}
