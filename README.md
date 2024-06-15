# AdaptiveGC Plugin

AdaptiveGC is a plugin for automatic garbage collection (GC) management in PocketMine-MP servers. It dynamically
monitors server conditions and decides whether to run the GC based on various configurable factors.

## Features

- **Automatic Garbage Collection**: Monitors and manages garbage collection automatically based on server conditions.
- **Dynamic Configuration**: Adjustable settings for fine-tuning GC behavior.
- **Performance Optimization**: Aims to optimize server performance by moving GC to a sweet spot.

## Configuration

The plugin supports the following configuration options in `config.yml`:

```yaml
#Will only run gc when tick used percentage is lower or equal to given value (default 10).
trigger-percentage: 10
#Will cancel gc if gc may exceed server tick time.
avoid-time-exceed: true
#Will only run gc when player count is 0 (default true).
trigger-no-player: true
#Will run gc unconditionally if root count exceeds the given value (default 500000).
force-root-count: 500000
#Will skip gc if root count increment is lower than the given ratio.
gc-skip-threshold-ratio: 0.01
#Prediction EMA(Exponential Moving Average) smoothing factor (default 0.3).
#The smoothing factor determines the weight given to the most recent data points.
#It controls how sensitive the EMA is to recent changes in the data.
smoothing-factor: 0.5
```

## Usage

AdaptiveGC runs automatically once installed and enabled. It monitors server tick timing and garbage collection metrics
to make informed decisions about when to perform GC operations.

## Commands

- **gc_status**: show the current status of garbage collector.
- **adaptive_gc_reload**: reload config.

## Permissions

- **adaptive_gc.gc_status**: op
- **adaptive_gc.adaptive_gc_reload**: op

## Troubleshooting

- **Logging**: Check server logs for any errors or warnings by AdaptiveGC.
- **Configuration**: Verify that `config.yml` is correctly configured.

## License

This plugin is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
