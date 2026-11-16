<?php

namespace App\Notifications;

use App\Models\ContactReply;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Mail aan de admins: een gebruiker stelde een vraag via Contact of reageerde in een gesprek */
class NewContactMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactReply $reply) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** @param  User  $notifiable */
    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->reply->request;
        $first = $request->replies()->value('id') === $this->reply->id;

        return (new MailMessage)
            ->subject(($first ? __('New question') : __('New reaction')).': '.$request->subject)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($first
                ? __(':name asked a question via Contact (:topic).', ['name' => $request->user->name, 'topic' => $request->topic->label()])
                : __(':name responded in the conversation.', ['name' => $request->user->name]))
            ->line('“'.Str::limit($this->reply->body, 500).'”')
            ->action(__('Open in Contact'), route('admin.contact', ['open' => $request->id]))
            ->salutation(__('Regards, :app', ['app' => config('app.name')]));
    }
}
