import { Response } from 'express';

/**
 * Format and send an error response with consistent structure
 * @param res Express response object
 * @param error Error object or string
 * @param defaultMessage Default message if error doesn't provide one
 * @param statusCode HTTP status code (default: 500)
 */
export function formatErrorResponse(
  res: Response, 
  error: any, 
  defaultMessage: string = 'An error occurred', 
  statusCode: number = 500
): Response {
  console.error(`[API Error] ${defaultMessage}:`, error);
  
  // Extract message from error object or use default
  const errorMessage = error?.message || error?.toString() || defaultMessage;
  
  // Determine status code - some errors might include a specific code
  const finalStatus = error?.statusCode || statusCode;
  
  return res.status(finalStatus).json({
    success: false,
    message: errorMessage,
    error: process.env.NODE_ENV === 'development' 
      ? { stack: error?.stack } 
      : undefined
  });
}

/**
 * Format and send a success response with consistent structure
 * @param res Express response object
 * @param data Data to return
 * @param message Success message
 * @param statusCode HTTP status code (default: 200)
 */
export function formatSuccessResponse(
  res: Response, 
  data: any = null, 
  message: string = 'Operation successful', 
  statusCode: number = 200
): Response {
  return res.status(statusCode).json({
    success: true,
    message,
    data
  });
} 