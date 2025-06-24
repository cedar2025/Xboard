<?php
namespace App\Logging;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use App\Models\Log as LogModel;
use Monolog\LogRecord;

class MysqlLoggerHandler extends AbstractProcessingHandler
{
    public function __construct($level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $record = $record->toArray();
        try {
            if (isset($record['context']['exception']) && is_object($record['context']['exception'])) {
                $record['context']['exception'] = (array)$record['context']['exception'];
            }
            
            // Filter out sensitive fields from request data
            $requestData = request()->all();
            $sensitiveFields = ['password', 'password_confirmation', 'current_password', 'new_password', 'auth_data', 'token'];
            foreach ($sensitiveFields as $field) {
                if (isset($requestData[$field])) {
                    $requestData[$field] = '[FILTERED]';
                }
            }
            $record['request_data'] = $requestData;
            
            $log = [
                'title' => $record['message'],
                'level' => $record['level_name'],
                'host' => $record['extra']['request_host'] ?? request()->getSchemeAndHttpHost(),
                'uri' => $record['extra']['request_uri'] ?? request()->getRequestUri(),
                'method' => $record['extra']['request_method'] ?? request()->getMethod(),
                'ip' => request()->getClientIp(),
                'data' => json_encode($record['request_data']),
                'context' => json_encode($record['context']),
                'created_at' => $record['datetime']->getTimestamp(),
                'updated_at' => $record['datetime']->getTimestamp(),
            ];
            
            LogModel::insert($log);
        } catch (\Exception $e) {
            // Log::channel('daily')->error($e->getMessage().$e->getFile().$e->getTraceAsString());
        }
    }
}
