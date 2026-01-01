/**
 * Chat Module - Real-time messaging using Laravel Echo
 * Replaces Livewire polling with WebSocket-based updates
 */

class ChatManager {
    constructor(taskId, taskUuid) {
        this.taskId = taskId;
        this.taskUuid = taskUuid;
        this.channel = null;
        this.messages = new Map();
        this.isConnected = false;
        this.onMessageCreated = null;
        this.onMessageUpdated = null;
        this.onTaskStatusChanged = null;
    }

    /**
     * Connect to the task's private channel
     */
    connect() {
        if (!window.Echo) {
            console.error('Laravel Echo not initialized');
            return;
        }

        this.channel = window.Echo.private(`task.${this.taskId}`);

        this.channel
            .listen('.message.created', (data) => {
                this.handleMessageCreated(data);
            })
            .listen('.message.updated', (data) => {
                this.handleMessageUpdated(data);
            })
            .listen('.task.status', (data) => {
                this.handleTaskStatusChanged(data);
            });

        this.isConnected = true;
        console.log(`Connected to task.${this.taskId} channel`);
    }

    /**
     * Disconnect from the channel
     */
    disconnect() {
        if (this.channel) {
            window.Echo.leave(`task.${this.taskId}`);
            this.channel = null;
            this.isConnected = false;
        }
    }

    /**
     * Handle new message created
     */
    handleMessageCreated(data) {
        this.messages.set(data.id, data);

        if (this.onMessageCreated) {
            this.onMessageCreated(data);
        }

        // Dispatch custom event for Alpine.js components
        document.dispatchEvent(new CustomEvent('chat:message-created', { detail: data }));
    }

    /**
     * Handle message updated (content, tool calls, etc.)
     */
    handleMessageUpdated(data) {
        this.messages.set(data.id, data);

        if (this.onMessageUpdated) {
            this.onMessageUpdated(data);
        }

        // Dispatch custom event for Alpine.js components
        document.dispatchEvent(new CustomEvent('chat:message-updated', { detail: data }));
    }

    /**
     * Handle task status changes (running, completed, failed)
     */
    handleTaskStatusChanged(data) {
        if (this.onTaskStatusChanged) {
            this.onTaskStatusChanged(data);
        }

        // Dispatch custom event for Alpine.js components
        document.dispatchEvent(new CustomEvent('chat:task-status', { detail: data }));
    }

    /**
     * Send a message via API
     */
    async sendMessage(prompt, images = []) {
        const response = await fetch(`/api/chat/${this.taskUuid}/messages`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ prompt, images }),
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || 'Failed to send message');
        }

        return response.json();
    }

    /**
     * Get task status
     */
    async getStatus() {
        const response = await fetch(`/api/chat/${this.taskUuid}/status`, {
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to get status');
        }

        return response.json();
    }

    /**
     * Get queued messages
     */
    async getQueuedMessages() {
        const response = await fetch(`/api/chat/${this.taskUuid}/queued`, {
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to get queued messages');
        }

        return response.json();
    }

    /**
     * Delete a queued message
     */
    async deleteQueuedMessage(messageId) {
        const response = await fetch(`/api/chat/${this.taskUuid}/queued/${messageId}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to delete message');
        }

        return response.json();
    }

    /**
     * Submit question response
     */
    async submitQuestionResponse(messageId, toolId, responses) {
        const response = await fetch(`/api/chat/${this.taskUuid}/messages/${messageId}/question-response`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ tool_id: toolId, responses }),
        });

        if (!response.ok) {
            throw new Error('Failed to submit response');
        }

        return response.json();
    }
}

// Export for use in Alpine.js or other JS
window.ChatManager = ChatManager;

// Alpine.js component for realtime chat updates
document.addEventListener('alpine:init', () => {
    Alpine.data('realtimeChat', (taskId, taskUuid, initialStatus = 'pending') => ({
        taskId: taskId,
        taskUuid: taskUuid,
        taskStatus: initialStatus,
        isCompacting: false,
        compactionCount: 0,
        chatManager: null,
        optimisticMessage: null,

        init() {
            // Connect to WebSocket
            this.chatManager = new ChatManager(taskId, taskUuid);

            this.chatManager.onMessageCreated = (data) => {
                // Clear optimistic message when real message arrives
                if (data.role === 'user') {
                    this.optimisticMessage = null;
                }
                // Let the message component handle rendering
                this.$dispatch('message-created', data);
            };

            this.chatManager.onMessageUpdated = (data) => {
                // Let the message component handle updates
                this.$dispatch('message-updated', data);
            };

            this.chatManager.onTaskStatusChanged = (data) => {
                this.taskStatus = data.status;
                this.isCompacting = data.is_compacting;
                this.compactionCount = data.compaction_count;
                this.$dispatch('task-status-changed', data);
            };

            this.chatManager.connect();
        },

        destroy() {
            if (this.chatManager) {
                this.chatManager.disconnect();
            }
        },

        get isRunning() {
            return this.taskStatus === 'running';
        },

        showOptimisticMessage(content, images = []) {
            this.optimisticMessage = {
                content: content,
                images: images,
                timestamp: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false })
            };
        },

        async sendMessage(prompt, images = []) {
            try {
                const result = await this.chatManager.sendMessage(prompt, images);
                return result;
            } catch (error) {
                console.error('Failed to send message:', error);
                throw error;
            }
        },

        async deleteQueuedMessage(messageId) {
            try {
                await this.chatManager.deleteQueuedMessage(messageId);
                this.$dispatch('queued-message-deleted', { id: messageId });
            } catch (error) {
                console.error('Failed to delete queued message:', error);
            }
        },

        async submitQuestionResponse(messageId, toolId, responses) {
            try {
                const result = await this.chatManager.submitQuestionResponse(messageId, toolId, responses);
                return result;
            } catch (error) {
                console.error('Failed to submit response:', error);
                throw error;
            }
        }
    }));

    // Message display component - handles individual message rendering
    Alpine.data('chatMessage', (message) => ({
        message: message,

        init() {
            // Listen for updates to this specific message
            document.addEventListener('chat:message-updated', (e) => {
                if (e.detail.id === this.message.id) {
                    this.message = { ...this.message, ...e.detail };
                }
            });
        },

        get isUser() {
            return this.message.role === 'user';
        },

        get isAssistant() {
            return this.message.role === 'assistant';
        },

        get formattedTime() {
            const date = new Date(this.message.created_at);
            return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
        }
    }));

    // Messages list component - manages the list of messages
    Alpine.data('chatMessages', (initialMessages = []) => ({
        messages: initialMessages,
        isNearBottom: true,
        scrollThreshold: 150,

        init() {
            // Scroll to bottom on init
            this.$nextTick(() => this.scrollToBottom());

            // Add scroll listener
            if (this.$refs.messages) {
                this.$refs.messages.addEventListener('scroll', () => this.checkIfNearBottom());
            }

            // Listen for new messages
            document.addEventListener('chat:message-created', (e) => {
                this.addOrUpdateMessage(e.detail);
            });

            document.addEventListener('chat:message-updated', (e) => {
                this.addOrUpdateMessage(e.detail);
            });
        },

        addOrUpdateMessage(data) {
            const existingIndex = this.messages.findIndex(m => m.id === data.id);
            if (existingIndex >= 0) {
                this.messages[existingIndex] = { ...this.messages[existingIndex], ...data };
            } else {
                this.messages.push(data);
            }

            if (this.isNearBottom) {
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        checkIfNearBottom() {
            const el = this.$refs.messages;
            if (el) {
                this.isNearBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) < this.scrollThreshold;
            }
        },

        scrollToBottom() {
            const el = this.$refs.messages;
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        }
    }));
});
