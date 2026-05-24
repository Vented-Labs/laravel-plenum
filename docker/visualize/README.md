# Dashboard preview

A throwaway container that boots the Plenum dashboard against fake-host node pools so you can review the UI without standing up real databases.

```bash
docker compose -f docker/visualize/compose.yml up --build
```

Then open <http://localhost:8000/plenum>.

The container pre-marks `db_2` and `redis_1` as down so the dashboard has a visible mix of UP/DOWN states the moment it loads. The node hosts are not reachable on purpose — running `plenum:probe` inside the container will mark every node down:

```bash
docker compose -f docker/visualize/compose.yml exec dashboard vendor/bin/testbench plenum:probe
```

Tear down with `Ctrl+C` and `docker compose -f docker/visualize/compose.yml down`.
