<?php

namespace App\Services;

use App\Models\Task;
use App\Models\NotificationRule;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class KanbanNotificationService
{
    public function handleStageEntered(Task $task, string $trigger = 'on_enter_stage'): void
    {
        $rules = NotificationRule::query()
            ->where('stage_id', $task->stage_id)
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        foreach ($rules as $rule) {
            $this->processRule($rule, $task);
        }
    }

    protected function processRule(NotificationRule $rule, Task $task): void
    {
        $recipients = $this->buildRecipients($rule, $task);

        if (empty($recipients)) {
            return;
        }

        $template = $rule->messageTemplate;

        if (! $template) {
            return;
        }

        // contexto para placeholders
        $context = $this->buildContext($task);

        $subject = $this->renderTemplate($template->subject, $context);
        $body    = $this->renderTemplate($template->body, $context);

        foreach ($recipients as $email) {
            if (! $this->shouldSendToRecipient($rule, $task, $email)) {
                continue;
            }

            $this->sendEmail($rule, $task, $email, $subject, $body);
        }
    }

    /**
     * Junta emails da RecipientList + assigned user (se configurado).
     */
    protected function buildRecipients(NotificationRule $rule, Task $task): array
    {
        $emails = [];

        if ($rule->recipientList) {
            $emails = array_merge(
                $emails,
                $rule->recipientList->contacts->pluck('email')->all()
            );
        }

        if ($rule->also_send_to_assigned_user && $task->assignedTo?->email) {
            $emails[] = $task->assignedTo->email;
        }

        // remover duplicados e vazios
        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Decide se deve enviar email consoante o send_mode.
     */
    protected function shouldSendToRecipient(NotificationRule $rule, Task $task, string $email): bool
    {
        $query = NotificationLog::query()
            ->where('notification_rule_id', $rule->id)
            ->where('task_id', $task->id)
            ->where('to_email', $email)
            ->where('status', 'sent');

        if ($rule->send_mode === 'first_time') {
            return ! $query->exists();
        }

        if ($rule->send_mode === 'cooldown' && $rule->cooldown_hours) {
            $last = $query->latest('sent_at')->first();
            if (! $last) {
                return true;
            }

            $diff = $last->sent_at?->diffInHours(now()) ?? PHP_INT_MAX;

            return $diff >= $rule->cooldown_hours;
        }

        // 'always'
        return true;
    }

    protected function sendEmail(NotificationRule $rule, Task $task, string $email, string $subject, string $body): void
    {
        $log = new NotificationLog([
            'notification_rule_id' => $rule->id,
            'task_id'              => $task->id,
            'to_email'             => $email,
            'subject'              => $subject,
            'status'               => 'pending',
        ]);
        $log->save();

        try {
            if ($rule->messageTemplate->is_html) {
                Mail::html($body, function ($message) use ($email, $subject) {
                    $message->to($email)
                        ->subject($subject);
                });
            } else {
                Mail::raw(strip_tags($body), function ($message) use ($email, $subject) {
                    $message->to($email)
                        ->subject($subject);
                });
            }

            $log->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
        }
    }

    /**
     * Contexto de placeholders para o template.
     */
    protected function buildContext(Task $task): array
    {
        return [
            'task.id'          => $task->id,
            'task.title'       => $task->title,
            'task.description' => $task->description,
            'task.priority'    => $task->priority,
            'task.due_at'      => optional($task->due_at)->format('Y-m-d H:i'),
            'board.name'       => $task->board?->name,
            'stage.name'       => $task->stage?->name,
            'user.name'        => $task->assignedTo?->name,
            'user.email'       => $task->assignedTo?->email,
            'app.url'          => config('app.url'),
        ];
    }

    /**
     * Render básico de placeholders {{key}}.
     */
    protected function renderTemplate(string $text, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            $replace['{{' . $key . '}}'] = $value ?? '';
        }

        return strtr($text, $replace);
    }
}
