<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\WhatsAppTool;
use Illuminate\Support\Facades\Log;

class FlowService
{
    /**
     * Get the current flow state for a conversation
     */
    public function getFlowState(Conversation $conversation): ?array
    {
        return $conversation->conversation_state ?? null;
    }

    /**
     * Set the flow state for a conversation
     */
    public function setFlowState(Conversation $conversation, array $state): void
    {
        $conversation->update(['conversation_state' => $state]);
    }

    /**
     * Clear the flow state for a conversation
     */
    public function clearFlowState(Conversation $conversation): void
    {
        $conversation->update(['conversation_state' => null]);
    }

    /**
     * Check if a conversation is in a flow
     */
    public function isInFlow(Conversation $conversation): bool
    {
        $state = $this->getFlowState($conversation);
        return $state !== null && isset($state['active_flow']) && $state['active_flow'] !== null;
    }

    /**
     * Get the current step of a flow
     */
    public function getCurrentStep(Conversation $conversation): ?array
    {
        $state = $this->getFlowState($conversation);
        if (!$state || !isset($state['active_flow']) || !isset($state['current_step'])) {
            return null;
        }

        $tool = WhatsAppTool::find($state['active_flow']);
        if (!$tool || !$tool->flow_config) {
            return null;
        }

        $steps = $tool->flow_config['steps'] ?? [];
        $currentStepIndex = $state['current_step'];
        
        return $steps[$currentStepIndex] ?? null;
    }

    /**
     * Get collected data from flow state
     */
    public function getCollectedData(Conversation $conversation): array
    {
        $state = $this->getFlowState($conversation);
        return $state['collected_data'] ?? [];
    }

    /**
     * Set collected data in flow state
     */
    public function setCollectedData(Conversation $conversation, string $key, $value): void
    {
        $state = $this->getFlowState($conversation) ?? [];
        if (!isset($state['collected_data'])) {
            $state['collected_data'] = [];
        }
        $state['collected_data'][$key] = $value;
        $this->setFlowState($conversation, $state);
    }

    /**
     * Start a flow for a conversation
     */
    public function startFlow(Conversation $conversation, WhatsAppTool $tool): void
    {
        $flowConfig = $tool->flow_config;
        if (!$flowConfig || empty($flowConfig['steps'])) {
            Log::warning('Attempted to start flow without steps', [
                'tool_id' => $tool->id,
                'conversation_id' => $conversation->id,
            ]);
            return;
        }

        $state = [
            'active_flow' => $tool->id,
            'current_step' => 0,
            'collected_data' => [],
            'started_at' => now()->toIso8601String(),
        ];

        $this->setFlowState($conversation, $state);
        
        Log::info('Flow started', [
            'tool_id' => $tool->id,
            'tool_name' => $tool->name,
            'conversation_id' => $conversation->id,
            'total_steps' => count($flowConfig['steps']),
        ]);
    }

    /**
     * Move to the next step in the flow
     */
    public function nextStep(Conversation $conversation): ?array
    {
        $state = $this->getFlowState($conversation);
        if (!$state || !isset($state['active_flow']) || !isset($state['current_step'])) {
            return null;
        }

        $tool = WhatsAppTool::find($state['active_flow']);
        if (!$tool || !$tool->flow_config) {
            return null;
        }

        $steps = $tool->flow_config['steps'] ?? [];
        $currentStepIndex = $state['current_step'];
        $nextStepIndex = $currentStepIndex + 1;

        if ($nextStepIndex >= count($steps)) {
            // Flow completed
            $this->completeFlow($conversation);
            return null;
        }

        $state['current_step'] = $nextStepIndex;
        $this->setFlowState($conversation, $state);

        return $steps[$nextStepIndex] ?? null;
    }

    /**
     * Complete the current flow
     */
    public function completeFlow(Conversation $conversation): array
    {
        $state = $this->getFlowState($conversation);
        $collectedData = $state['collected_data'] ?? [];
        
        $this->clearFlowState($conversation);

        Log::info('Flow completed', [
            'conversation_id' => $conversation->id,
            'collected_data_keys' => array_keys($collectedData),
        ]);

        return $collectedData;
    }

    /**
     * Check if all required data for current step is collected
     */
    public function isStepComplete(Conversation $conversation): bool
    {
        $currentStep = $this->getCurrentStep($conversation);
        if (!$currentStep) {
            return false;
        }

        $collectedData = $this->getCollectedData($conversation);
        $requiredFields = $currentStep['required_fields'] ?? [];

        foreach ($requiredFields as $field) {
            if (!isset($collectedData[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the question/prompt for the current step
     */
    public function getCurrentStepPrompt(Conversation $conversation): ?string
    {
        $currentStep = $this->getCurrentStep($conversation);
        return $currentStep['prompt'] ?? null;
    }

    /**
     * Process user response and extract data based on step configuration
     */
    public function processStepResponse(Conversation $conversation, string $userResponse): array
    {
        $currentStep = $this->getCurrentStep($conversation);
        if (!$currentStep) {
            return ['success' => false, 'error' => 'No active step'];
        }

        $extractedData = [];
        $fieldMappings = $currentStep['field_mappings'] ?? [];

        foreach ($fieldMappings as $field => $config) {
            $extractType = $config['type'] ?? 'text';
            $value = null;

            switch ($extractType) {
                case 'text':
                    // Extract as plain text
                    $value = trim($userResponse);
                    break;
                case 'number':
                    // Extract numbers from response
                    preg_match('/\d+/', $userResponse, $matches);
                    $value = $matches[0] ?? null;
                    break;
                case 'regex':
                    // Extract using regex pattern
                    $pattern = $config['pattern'] ?? null;
                    if ($pattern && preg_match($pattern, $userResponse, $matches)) {
                        $value = $matches[1] ?? $matches[0] ?? null;
                    }
                    break;
                case 'keyword':
                    // Extract based on keywords
                    $keywords = $config['keywords'] ?? [];
                    $userResponseLower = mb_strtolower($userResponse);
                    foreach ($keywords as $keyword => $keywordValue) {
                        if (str_contains($userResponseLower, mb_strtolower($keyword))) {
                            $value = $keywordValue;
                            break;
                        }
                    }
                    break;
            }

            if ($value !== null) {
                $extractedData[$field] = $value;
                $this->setCollectedData($conversation, $field, $value);
            }
        }

        return [
            'success' => true,
            'extracted_data' => $extractedData,
            'step_complete' => $this->isStepComplete($conversation),
        ];
    }
}
