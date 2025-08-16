<?php

namespace App\Http\Controllers;

use App\Models\InboundTemplate;
use App\Models\Pannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InboundTemplateController extends Controller
{
    /**
     * Create a new inbound template from user input
     */
    public function createFromUserInput(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pannel_id' => 'required|exists:pannels,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'user_input' => 'required|string', // User provided inbound configuration
                'created_by' => 'nullable|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Parse user input to extract configuration
            $parsedConfig = $this->parseUserInput($request->user_input);
            
            if (!$parsedConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid inbound configuration format'
                ], 400);
            }

            // Create template
            $template = InboundTemplate::create([
                'pannel_id' => $request->pannel_id,
                'name' => $request->name,
                'description' => $request->description,
                'inbound_config' => $parsedConfig,
                'protocol' => $parsedConfig['protocol'],
                'port' => $parsedConfig['port'],
                'stream_settings' => $parsedConfig['streamSettings'] ?? null,
                'settings' => $parsedConfig['settings'] ?? null,
                'is_active' => true,
                'created_by' => $request->created_by
            ]);

            Log::info("Inbound template created", [
                'template_id' => $template->id,
                'panel_id' => $request->pannel_id,
                'protocol' => $parsedConfig['protocol']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Create template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Parse user input to extract inbound configuration
     */
    private function parseUserInput(string $userInput): ?array
    {
        try {
            // Try to parse as JSON first
            if (str_starts_with(trim($userInput), '{')) {
                $config = json_decode($userInput, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->normalizeConfig($config);
                }
            }

            // Try to parse as URL format (vless://, vmess://, trojan://)
            if (preg_match('/^(vless|vmess|trojan):\/\/(.+)$/i', $userInput, $matches)) {
                return $this->parseUrlFormat($userInput);
            }

            // Try to parse as base64 encoded VMESS
            if (str_starts_with($userInput, 'vmess://')) {
                return $this->parseVmessUrl($userInput);
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Parse user input error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse URL format configurations
     */
    private function parseUrlFormat(string $url): ?array
    {
        try {
            if (preg_match('/^(vless|vmess|trojan):\/\/([^@]+)@([^:]+):(\d+)(\?.*)?#(.+)$/i', $url, $matches)) {
                $protocol = strtolower($matches[1]);
                $uuid = $matches[2];
                $host = $matches[3];
                $port = (int) $matches[4];
                $query = $matches[5] ?? '';
                $remark = urldecode($matches[6]);

                $queryParams = [];
                if ($query) {
                    parse_str(ltrim($query, '?'), $queryParams);
                }

                $config = [
                    'id' => uniqid('template_'),
                    'protocol' => $protocol,
                    'port' => $port,
                    'settings' => [
                        'clients' => [[
                            'id' => $uuid,
                            'email' => $remark
                        ]]
                    ],
                    'streamSettings' => [
                        'network' => $queryParams['type'] ?? 'tcp',
                        'security' => $queryParams['security'] ?? null
                    ]
                ];

                // Handle WebSocket settings
                if (($queryParams['type'] ?? '') === 'ws') {
                    $config['streamSettings']['wsSettings'] = [
                        'path' => $queryParams['path'] ?? '/',
                        'headers' => [
                            'Host' => $queryParams['host'] ?? $host
                        ]
                    ];
                }

                // Handle gRPC settings
                if (($queryParams['type'] ?? '') === 'grpc') {
                    $config['streamSettings']['grpcSettings'] = [
                        'serviceName' => $queryParams['serviceName'] ?? ''
                    ];
                }

                return $config;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Parse URL format error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse VMESS URL (base64 encoded)
     */
    private function parseVmessUrl(string $url): ?array
    {
        try {
            $encoded = str_replace('vmess://', '', $url);
            $decoded = base64_decode($encoded);
            if ($decoded === false) {
                return null;
            }

            $config = json_decode($decoded, true);
            if (!$config || json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $this->normalizeConfig($config);

        } catch (\Exception $e) {
            Log::error('Parse VMESS URL error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize configuration structure
     */
    private function normalizeConfig(array $config): array
    {
        $normalized = [
            'id' => $config['id'] ?? uniqid('template_'),
            'protocol' => strtolower($config['protocol'] ?? 'vless'),
            'port' => (int) ($config['port'] ?? 443),
            'settings' => $config['settings'] ?? [],
            'streamSettings' => $config['streamSettings'] ?? $config['stream_settings'] ?? []
        ];

        // Ensure required fields exist
        if (empty($normalized['settings']['clients'])) {
            $normalized['settings']['clients'] = [];
        }

        return $normalized;
    }

    /**
     * Get all templates for a panel
     */
    public function getTemplatesForPanel($panelId)
    {
        try {
            $templates = InboundTemplate::forPanel($panelId)
                ->active()
                ->with('creator')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            Log::error('Get templates error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Get template by ID
     */
    public function getTemplate($id)
    {
        try {
            $template = InboundTemplate::with(['panel', 'creator'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Get template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }
    }

    /**
     * Update template
     */
    public function updateTemplate(Request $request, $id)
    {
        try {
            $template = InboundTemplate::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $template->update($request->only(['name', 'description', 'is_active']));

            Log::info("Template updated", ['template_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Update template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Delete template
     */
    public function deleteTemplate($id)
    {
        try {
            $template = InboundTemplate::findOrFail($id);
            $template->delete();

            Log::info("Template deleted", ['template_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Test template configuration
     */
    public function testTemplate($id)
    {
        try {
            $template = InboundTemplate::findOrFail($id);
            
            if (!$template->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template configuration is invalid'
                ], 400);
            }

            // Here you could add actual connection testing logic
            $testResult = [
                'template_id' => $template->id,
                'protocol' => $template->protocol,
                'port' => $template->port,
                'is_valid' => $template->isValid(),
                'configuration' => $template->toInboundConfig()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Template test completed',
                'data' => $testResult
            ]);

        } catch (\Exception $e) {
            Log::error('Test template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }
}
