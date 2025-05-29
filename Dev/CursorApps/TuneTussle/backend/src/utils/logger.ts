export type LogLevel = 'debug' | 'info' | 'warn' | 'error';

export interface LoggerConfig {
  level: LogLevel;
  enableConsole: boolean;
  enableFile?: boolean;
  prefix?: string;
}

class Logger {
  private static instance: Logger;
  private config: LoggerConfig;
  private logLevels: Record<LogLevel, number> = {
    debug: 0,
    info: 1,
    warn: 2,
    error: 3
  };

  private constructor() {
    // Default configuration based on environment
    const isProduction = process.env.NODE_ENV === 'production';
    const isDevelopment = process.env.NODE_ENV === 'development';
    
    this.config = {
      level: isProduction ? 'error' : isDevelopment ? 'debug' : 'info',
      enableConsole: !isProduction,
      prefix: '[TuneTussle]'
    };
  }

  public static getInstance(): Logger {
    if (!Logger.instance) {
      Logger.instance = new Logger();
    }
    return Logger.instance;
  }

  public configure(config: Partial<LoggerConfig>): void {
    this.config = { ...this.config, ...config };
  }

  private shouldLog(level: LogLevel): boolean {
    return this.logLevels[level] >= this.logLevels[this.config.level];
  }

  private formatMessage(level: LogLevel, message: string, context?: string): string {
    const timestamp = new Date().toISOString();
    const prefix = this.config.prefix || '';
    const contextStr = context ? ` [${context}]` : '';
    return `${timestamp} ${prefix}${contextStr} [${level.toUpperCase()}] ${message}`;
  }

  private log(level: LogLevel, message: string, data?: any, context?: string): void {
    if (!this.shouldLog(level) || !this.config.enableConsole) {
      return;
    }

    const formattedMessage = this.formatMessage(level, message, context);
    
    switch (level) {
      case 'debug':
        console.log(formattedMessage, data || '');
        break;
      case 'info':
        console.log(formattedMessage, data || '');
        break;
      case 'warn':
        console.warn(formattedMessage, data || '');
        break;
      case 'error':
        console.error(formattedMessage, data || '');
        break;
    }
  }

  public debug(message: string, data?: any, context?: string): void {
    this.log('debug', message, data, context);
  }

  public info(message: string, data?: any, context?: string): void {
    this.log('info', message, data, context);
  }

  public warn(message: string, data?: any, context?: string): void {
    this.log('warn', message, data, context);
  }

  public error(message: string, data?: any, context?: string): void {
    this.log('error', message, data, context);
  }

  // Convenience methods for common contexts
  public socket(message: string, data?: any): void {
    this.debug(message, data, 'Socket');
  }

  public game(message: string, data?: any): void {
    this.debug(message, data, 'Game');
  }

  public api(message: string, data?: any): void {
    this.info(message, data, 'API');
  }

  public service(message: string, data?: any): void {
    this.debug(message, data, 'Service');
  }

  public timer(message: string, data?: any): void {
    this.debug(message, data, 'Timer');
  }

  public llm(message: string, data?: any): void {
    this.debug(message, data, 'LLM');
  }

  public youtube(message: string, data?: any): void {
    this.debug(message, data, 'YouTube');
  }

  // Performance tracking
  public performance(operation: string, startTime: number, context?: string): void {
    const duration = Date.now() - startTime;
    this.debug(`${operation} completed in ${duration}ms`, undefined, context || 'Performance');
  }

  // Game event logging with structured data
  public gameEvent(event: string, gameCode: string, data?: any): void {
    this.info(`Game Event: ${event}`, { gameCode, ...data }, 'GameEvent');
  }
}

// Export singleton instance
export const logger = Logger.getInstance();

// Export factory function for creating contextual loggers
export function createContextLogger(context: string) {
  return {
    debug: (message: string, data?: any) => logger.debug(message, data, context),
    info: (message: string, data?: any) => logger.info(message, data, context),
    warn: (message: string, data?: any) => logger.warn(message, data, context),
    error: (message: string, data?: any) => logger.error(message, data, context),
  };
} 