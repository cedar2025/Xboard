<?php

namespace App\Http\Controllers\V1\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\TicketSave;
use App\Http\Requests\User\TicketWithdraw;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\TicketService;
use App\Utils\Dict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function fetch(Request $request)
    {
        if ($request->input('id')) {
            $ticket = Ticket::where('id', $request->input('id'))
                ->where('user_id', $request->user['id'])
                ->first();
            if (!$ticket) {
                throw new ApiException(500, __('Ticket does not exist'));
            }
            $ticket['message'] = TicketMessage::where('ticket_id', $ticket->id)->get();
            for ($i = 0; $i < count($ticket['message']); $i++) {
                if ($ticket['message'][$i]['user_id'] == $ticket->user_id) {
                    $ticket['message'][$i]['is_me'] = true;
                } else {
                    $ticket['message'][$i]['is_me'] = false;
                }
            }
            return response([
                'data' => $ticket
            ]);
        }
        $ticket = Ticket::where('user_id', $request->user['id'])
            ->orderBy('created_at', 'DESC')
            ->get();
        return response([
            'data' => $ticket
        ]);
    }

    public function save(TicketSave $request)
    {
        try{
            DB::beginTransaction();
            if ((int)Ticket::where('status', 0)->where('user_id', $request->user['id'])->lockForUpdate()->count()) {
                throw new ApiException(500, __('There are other unresolved tickets'));
            }
            $ticket = Ticket::create(array_merge($request->only([
                'subject',
                'level'
            ]), [
                'user_id' => $request->user['id']
            ]));
            if (!$ticket) {
                throw new ApiException(500, __('Failed to open ticket'));
            }
            $ticketMessage = TicketMessage::create([
                'user_id' => $request->user['id'],
                'ticket_id' => $ticket->id,
                'message' => $request->input('message')
            ]);
            if (!$ticketMessage) {
                throw new ApiException(500, __('Failed to open ticket'));
            }
            DB::commit();
        }catch(\Exception $e){
            DB::rollBack();
            throw $e;
        }
        $this->sendNotify($ticket, $request->input('message'));
        return response([
            'data' => true
        ]);
    }

    public function reply(Request $request)
    {
        if (empty($request->input('id'))) {
            throw new ApiException(500, __('Invalid parameter'));
        }
        if (empty($request->input('message'))) {
            throw new ApiException(500, __('Message cannot be empty'));
        }
        $ticket = Ticket::where('id', $request->input('id'))
            ->where('user_id', $request->user['id'])
            ->first();
        if (!$ticket) {
            throw new ApiException(500, __('Ticket does not exist'));
        }
        if ($ticket->status) {
            throw new ApiException(500, __('The ticket is closed and cannot be replied'));
        }
        if ($request->user['id'] == $this->getLastMessage($ticket->id)->user_id) {
            throw new ApiException(500, __('Please wait for the technical enginneer to reply'));
        }
        $ticketService = new TicketService();
        if (!$ticketService->reply(
            $ticket,
            $request->input('message'),
            $request->user['id']
        )) {
            throw new ApiException(500, __('Ticket reply failed'));
        }
        $this->sendNotify($ticket, $request->input('message'));
        return response([
            'data' => true
        ]);
    }


    public function close(Request $request)
    {
        if (empty($request->input('id'))) {
            throw new ApiException(500, __('Invalid parameter'));
        }
        $ticket = Ticket::where('id', $request->input('id'))
            ->where('user_id', $request->user['id'])
            ->first();
        if (!$ticket) {
            throw new ApiException(500, __('Ticket does not exist'));
        }
        $ticket->status = 1;
        if (!$ticket->save()) {
            throw new ApiException(500, __('Close failed'));
        }
        return response([
            'data' => true
        ]);
    }

    private function getLastMessage($ticketId)
    {
        return TicketMessage::where('ticket_id', $ticketId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function withdraw(TicketWithdraw $request)
    {
        if ((int)admin_setting('withdraw_close_enable', 0)) {
            throw new ApiException(500, 'user.ticket.withdraw.not_support_withdraw');
        }
        if (!in_array(
            $request->input('withdraw_method'),
            admin_setting('commission_withdraw_method',Dict::WITHDRAW_METHOD_WHITELIST_DEFAULT)
        )) {
            throw new ApiException(500, __('Unsupported withdrawal method'));
        }
        $user = User::find($request->user['id']);
        $limit = admin_setting('commission_withdraw_limit', 100);
        if ($limit > ($user->commission_balance / 100)) {
            throw new ApiException(500, __('The current required minimum withdrawal commission is :limit', ['limit' => $limit]));
        }
        try{
            DB::beginTransaction();
            $subject = __('[Commission Withdrawal Request] This ticket is opened by the system');
            $ticket = Ticket::create([
                'subject' => $subject,
                'level' => 2,
                'user_id' => $request->user['id']
            ]);
            if (!$ticket) {
                throw new ApiException(500, __('Failed to open ticket'));
            }
            $message = sprintf("%s\r\n%s",
                __('Withdrawal method') . "：" . $request->input('withdraw_method'),
                __('Withdrawal account') . "：" . $request->input('withdraw_account')
            );
            $ticketMessage = TicketMessage::create([
                'user_id' => $request->user['id'],
                'ticket_id' => $ticket->id,
                'message' => $message
            ]);
            if (!$ticketMessage) {
                throw new ApiException(500, __('Failed to open ticket'));
            }
            DB::commit();
        }catch(\Exception $e){
            DB::rollBack();
            throw $e;
        }
        $this->sendNotify($ticket, $message);
        return response([
            'data' => true
        ]);
    }

    private function sendNotify(Ticket $ticket, string $message)
    {
        $telegramService = new TelegramService();
        $telegramService->sendMessageWithAdmin("📮工单提醒 #{$ticket->id}\n———————————————\n主题：\n`{$ticket->subject}`\n内容：\n`{$message}`", true);
    }
}
