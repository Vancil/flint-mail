<?php
declare(strict_types=1);

use Flint\Blueprint;
use Flint\Schema;

return new class {
    public function up(): void
    {
        Schema::create('queued_mails', function (Blueprint $table) {
            $table->id();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->string('subject');
            $table->longText('html_body')->nullable();
            $table->text('text_body')->nullable();
            $table->text('attachments')->nullable();
            $table->string('status')->default('pending');
            $table->integer('attempts')->default(0);
            $table->text('error')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queued_mails');
    }
};
