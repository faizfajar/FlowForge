import { onUnmounted, ref } from 'vue';
import type { StepRun, WorkflowRun } from '../types/run.types';

interface EchoPrivateChannel {
    listen: (event: string, callback: (payload: ReverbPayload) => void) => EchoPrivateChannel;
    stopListening: (event: string) => EchoPrivateChannel;
}

interface EchoClient {
    private: (channel: string) => EchoPrivateChannel;
    leave: (channel: string) => void;
}

interface ReverbPayload {
    run?: WorkflowRun;
    step_run?: StepRun | null;
}

interface RunCallbacks {
    onStepStarted?: (stepRun: StepRun) => void;
    onStepCompleted?: (stepRun: StepRun) => void;
    onStepFailed?: (stepRun: StepRun) => void;
    onRunCompleted?: (run: WorkflowRun) => void;
}

declare global {
    interface Window {
        Echo?: EchoClient;
    }
}

export function useReverb() {
    const connected = ref(false);
    const retryDelay = ref(1000);
    const timers: number[] = [];

    const scheduleReconnect = (callback: () => void): void => {
        const timer = window.setTimeout(() => {
            callback();
            retryDelay.value = Math.min(retryDelay.value * 2, 30000);
        }, retryDelay.value);
        timers.push(timer);
    };

    const subscribeToRun = (runId: string, tenantId: string, callbacks: RunCallbacks): void => {
        const channelName = `tenant.${tenantId}`;

        const subscribe = (): void => {
            if (window.Echo === undefined) {
                scheduleReconnect(subscribe);
                return;
            }

            const channel = window.Echo.private(channelName);
            connected.value = true;

            channel
                .listen('WorkflowStepStarted', (payload) => {
                    if (payload.run?.id === runId && payload.step_run !== null && payload.step_run !== undefined) {
                        callbacks.onStepStarted?.(payload.step_run);
                    }
                })
                .listen('WorkflowStepCompleted', (payload) => {
                    if (payload.run?.id === runId && payload.step_run !== null && payload.step_run !== undefined) {
                        callbacks.onStepCompleted?.(payload.step_run);
                    }
                })
                .listen('WorkflowStepFailed', (payload) => {
                    if (payload.run?.id === runId && payload.step_run !== null && payload.step_run !== undefined) {
                        callbacks.onStepFailed?.(payload.step_run);
                    }
                })
                .listen('WorkflowRunCompleted', (payload) => {
                    if (payload.run?.id === runId) {
                        callbacks.onRunCompleted?.(payload.run);
                    }
                });
        };

        subscribe();
    };

    onUnmounted(() => {
        timers.forEach((timer) => window.clearTimeout(timer));
    });

    return { connected, subscribeToRun };
}
