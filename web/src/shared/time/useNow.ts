import { useEffect, useState } from 'react';

const millisecondsPerSecond = 1000;

export function useNow(tickSeconds: number): Date {
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const timer = window.setInterval(() => {
      setNow(new Date());
    }, tickSeconds * millisecondsPerSecond);
    return () => {
      window.clearInterval(timer);
    };
  }, [tickSeconds]);
  return now;
}
