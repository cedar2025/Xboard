class VpnStopCoordinator<T> {
  Future<T>? _inFlight;

  bool get hasInFlight => _inFlight != null;

  Future<T> run(Future<T> Function() task) {
    final current = _inFlight;
    if (current != null) return current;

    final next = Future<T>.sync(task);
    _inFlight = next;
    next.then<void>(
      (_) => _clear(next),
      onError: (Object _, StackTrace __) => _clear(next),
    );
    return next;
  }

  void _clear(Future<T> completed) {
    if (identical(_inFlight, completed)) {
      _inFlight = null;
    }
  }
}
