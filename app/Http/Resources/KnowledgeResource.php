<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\Plugin\HookManager;

class KnowledgeResource extends JsonResource
{
  /**
   * Transform the resource into an array.
   *
   * @return array<string, mixed>
   */
  public function toArray(Request $request): array
  {
    $data = [
      'id' => $this['id'],
      'category' => $this['category'],
      'title' => $this['title'],
      'body' => $this->when(isset($this['body']), $this['body']),
      'updated_at' => $this['updated_at'],
    ];

    return HookManager::filter('user.knowledge.resource', $data, $request, $this);
  }
}
