<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    private function applyFiltersAndSorts(Request $request, $builder)
    {
        if ($request->has('filter')) {
            collect($request->input('filter'))->each(function ($filter) use ($builder) {
                $key = $filter['id'];
                $value = $filter['value'];
                $builder->where(function ($query) use ($key, $value) {
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, 'like', "%{$value}%");
                    }
                });
            });
        }

        if ($request->has('sort')) {
            collect($request->input('sort'))->each(function ($sort) use ($builder) {
                $key = $sort['id'];
                $value = $sort['desc'] ? 'DESC' : 'ASC';
                $builder->orderBy($key, $value);
            });
        }
    }
    public function fetch(Request $request)
    {
        if ($request->input('id')) {
            return $this->fetchTicketById($request);
        } else {
            return $this->fetchTickets($request);
        }
    }

    /**
     * Summary of fetchTicketById
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function fetchTicketById(Request $request)
    {
        $ticket = Ticket::with('messages', 'user')->find($request->input('id'));

        if (!$ticket) {
            return $this->fail([400202, '工单不存在']);
        }

        $ticket->messages->each(function ($message) use ($ticket) {
            $message->is_me = $message->user_id !== $ticket->user_id;
        });

        return $this->success($ticket);
    }

    /**
     * Summary of fetchTickets
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    private function fetchTickets(Request $request)
    {
        $ticketModel = Ticket::with('user')
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->has('reply_status'), function ($query) use ($request) {
                $query->whereIn('reply_status', $request->input('reply_status'));
            })
            ->when($request->has('email'), function ($query) use ($request) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('email', $request->input('email'));
                });
            });

        $this->applyFiltersAndSorts($request, $ticketModel);

        return response()->json($ticketModel
            ->latest('updated_at')
            ->paginate(
                perPage: $request->integer('pageSize', 10),
                page: $request->integer('current', 1)
            ));
    }

    public function reply(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric',
            'message' => 'required|string'
        ], [
            'id.required' => '工单ID不能为空',
            'message.required' => '消息不能为空'
        ]);
        $ticketService = new TicketService();
        $ticketService->replyByAdmin(
            $request->input('id'),
            $request->input('message'),
            $request->user()->id
        );
        return $this->success(true);
    }

    public function close(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => '工单ID不能为空'
        ]);
        try {
            $ticket = Ticket::findOrFail($request->input('id'));
            $ticket->status = Ticket::STATUS_CLOSED;
            $ticket->save();
            return $this->success(true);
        } catch (ModelNotFoundException $e) {
            return $this->fail([400202, '工单不存在']);
        } catch (\Exception $e) {
            return $this->fail([500101, '关闭失败']);
        }
    }
}
