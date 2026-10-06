import { useEffect, useState } from 'react';

export function useDebounced<Value>(value: Value, delayMilliseconds: number): Value {
  const [settled, setSettled] = useState(value);
  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSettled(value);
    }, delayMilliseconds);
    return () => {
      window.clearTimeout(timer);
    };
  }, [value, delayMilliseconds]);
  return settled;
}
