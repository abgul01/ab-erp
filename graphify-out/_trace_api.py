import json
from pathlib import Path

g = json.loads(Path('graphify-out/graph.json').read_text(encoding='utf-8'))
nodes = {n['id']: n for n in g['nodes']}
edges = g['links']

# Find ApiResponse concept node (the one with 258 edges)
api_response = None
for n_id, n in nodes.items():
    label = str(n.get('label', ''))
    nid = str(n.get('id', ''))
    if label == 'ApiResponse' and 'apiresponse' in nid:
        api_response = n
        break

if not api_response:
    for n_id, n in nodes.items():
        if 'apiresponse' in str(n_id).lower():
            api_response = n
            break

if not api_response:
    # search by source_location
    for n_id, n in nodes.items():
        src = str(n.get('source_location', ''))
        if 'ApiResponse' in src:
            api_response = n
            break

if api_response:
    print(f'=== ApiResponse Node ===')
    print(f'ID:   {api_response["id"]}')
    print(f'Label: {api_response.get("label", "(no label)")}')
    print(f'Type:  {api_response.get("type", "(no type)")}')
    print(f'Source: {api_response.get("source_location", "(no source)")}')
    print()
    
    neighbor_edges = []
    for e in edges:
        if e['source'] == api_response['id'] or e['target'] == api_response['id']:
            neighbor_edges.append(e)
    
    neighbor_ids = set()
    for e in neighbor_edges:
        if e['source'] == api_response['id']:
            neighbor_ids.add(e['target'])
        else:
            neighbor_ids.add(e['source'])
    
    # classify by type
    by_type = {}
    for nid in neighbor_ids:
        n = nodes.get(nid, {})
        ntype = n.get('type', 'unknown')
        by_type.setdefault(ntype, []).append(n.get('label', str(nid)))
    
    print(f'=== Connects to {len(neighbor_ids)} neighbors ({len(neighbor_edges)} edges) ===')
    for ntype, items in sorted(by_type.items()):
        print(f'\n[{ntype}] ({len(items)}):')
        for item in sorted(items)[:15]:
            print(f'  - {item}')
        if len(items) > 15:
            print(f'  ... and {len(items)-15} more')
    
    # show edge types
    rel_types = {}
    for e in neighbor_edges:
        rel = e.get('relation', e.get('label', 'unknown'))
        rel_types[rel] = rel_types.get(rel, 0) + 1
    print(f'\n=== Edge Types ===')
    for rel, cnt in sorted(rel_types.items(), key=lambda x: -x[1]):
        print(f'  {rel}: {cnt}')
else:
    print('ApiResponse node not found!')
